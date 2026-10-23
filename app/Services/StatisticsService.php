<?php

namespace App\Services;

use App\Enums\Order\PaymentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques du tableau de bord (Phase 6D). Règles de calcul validées par
 * Ahmed (documentées dans le journal de phase) :
 *
 * - Chiffre d'affaires = somme des `orders.total` dont le paiement est
 *   CONFIRMÉ, daté à l'encaissement (`payments.confirmed_at`), livraison
 *   incluse ; « marchandises » = sous-total articles moins remise. Remboursé
 *   = exclu (le statut n'est plus « confirmé »).
 * - Conversion = commandes passées / visiteurs uniques, sur la période.
 * - Panier abandonné : cf. Cart::scopeAbandoned / detectAbandonedCarts().
 *
 * Performance : les jours passés sont lus dans `daily_stats` (une ligne par
 * jour, calculée une fois) ; seul « aujourd'hui » est calculé en direct. Le
 * coût d'affichage ne dépend donc pas du volume d'historique.
 */
class StatisticsService
{
    /** Délai d'inactivité d'un panier avant de le dire abandonné. */
    public const ABANDON_AFTER_HOURS = 24;

    /**
     * Agrégats d'une période [from, to] (jours inclus) + période précédente
     * de même durée, pour la comparaison.
     *
     * @return array{current: array<string, float|int>, previous: array<string, float|int>}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();
        $length = (int) $from->diffInDays($to) + 1;

        return [
            'current' => $this->totals($from, $to),
            'previous' => $this->totals($from->subDays($length), $from->subDay()),
        ];
    }

    /**
     * Chiffre d'affaires jour par jour sur les N derniers jours (graphique).
     *
     * @return array<string, float> 'Y-m-d' => CA
     */
    public function dailyRevenue(int $days = 30): array
    {
        $to = CarbonImmutable::today();
        $from = $to->subDays($days - 1);
        $rows = $this->rows($from, $to);

        $series = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $series[$day->toDateString()] = (float) ($rows[$day->toDateString()]['revenue'] ?? 0);
        }

        return $series;
    }

    /**
     * Meilleures ventes sur la période, d'après les lignes de commandes PAYÉES
     * (prix et quantités figés à l'achat) — jamais le stock ni les vues.
     * Montant = prix × quantité avant remise (la remise est au niveau de la
     * commande, non ventilée par produit).
     *
     * @return list<object{product: string, quantity: int, revenue: float}>
     */
    public function topProducts(CarbonInterface $from, CarbonInterface $to, string $by = 'quantity', int $limit = 10): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->endOfDay();
        $order = $by === 'revenue' ? 'revenue' : 'quantity';
        $key = 'stats.top.'.$order.'.'.$from->toDateString().'.'.$to->toDateString().'.'.$limit;

        // Tableaux simples en cache (pas d'objets : cache.serializable_classes).
        return array_map(fn (array $row): object => (object) $row, Cache::remember($key, now()->addMinutes(10), fn (): array => DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('payments as p', 'p.order_id', '=', 'o.id')
            ->where('p.status', PaymentStatus::Confirmed->value)
            ->whereBetween('p.confirmed_at', [$from, $to])
            ->groupBy('i.product_id', 'i.product_name')
            ->selectRaw('i.product_name as product, SUM(i.quantity) as quantity, SUM(i.unit_price * i.quantity) as revenue')
            ->orderByDesc($order)
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'product' => $row->product,
                'quantity' => (int) $row->quantity,
                'revenue' => (float) $row->revenue,
            ])
            ->all()));
    }

    /**
     * Paniers abandonnés en cours (détectés et toujours non vides) et la
     * valeur des articles qu'ils contiennent, au prix catalogue actuel.
     *
     * @return array{count: int, value: float, customers: int}
     */
    public function abandonedCarts(): array
    {
        $row = DB::table('carts as c')
            ->join('cart_items as ci', 'ci.cart_id', '=', 'c.id')
            ->join('products as pr', 'pr.id', '=', 'ci.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'ci.product_variant_id')
            ->whereNotNull('c.abandoned_at')
            ->selectRaw('COUNT(DISTINCT c.id) as carts, COUNT(DISTINCT c.user_id) as customers, COALESCE(SUM(COALESCE(v.price_override, pr.price) * ci.quantity), 0) as value')
            ->first();

        return [
            'count' => (int) $row->carts,
            'value' => (float) $row->value,
            'customers' => (int) $row->customers,
        ];
    }

    /**
     * Marque les paniers abandonnés (tâche planifiée horaire) : non vides,
     * inchangés depuis 24 h, pas encore marqués, et sans commande passée par
     * leur propriétaire depuis la dernière modification. Requête directe
     * (sans événement) : n'efface pas le marqueur qu'elle pose.
     */
    public function detectAbandonedCarts(): int
    {
        return DB::table('carts')
            ->whereNull('abandoned_at')
            ->where('updated_at', '<=', now()->subHours(self::ABANDON_AFTER_HOURS))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('cart_items')->whereColumn('cart_items.cart_id', 'carts.id'))
            ->where(fn ($query) => $query
                ->whereNull('user_id')
                ->orWhereNotExists(fn ($orders) => $orders->selectRaw('1')->from('orders')
                    ->whereColumn('orders.user_id', 'carts.user_id')
                    ->whereColumn('orders.created_at', '>=', 'carts.updated_at')))
            ->update(['abandoned_at' => now()]);
    }

    /**
     * Calcule et enregistre l'agrégat d'un jour (idempotent : rejouable pour
     * absorber une correction tardive, ex. paiement confirmé après coup).
     */
    public function aggregateDay(CarbonInterface $day): void
    {
        $day = CarbonImmutable::parse($day)->startOfDay();

        DB::table('daily_stats')->upsert(
            ['day' => $day->toDateString()] + $this->compute($day) + ['computed_at' => now()],
            ['day'],
        );
    }

    /** @return array<string, float|int> */
    private function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = ['revenue' => 0.0, 'merchandise' => 0.0, 'paid_orders' => 0, 'placed_orders' => 0, 'visitors' => 0, 'abandoned_carts' => 0];

        foreach ($this->rows($from, $to) as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] += $row[$key];
            }
        }

        $totals['average_basket'] = $totals['paid_orders'] > 0 ? $totals['revenue'] / $totals['paid_orders'] : 0.0;
        $totals['conversion'] = $totals['visitors'] > 0 ? $totals['placed_orders'] / $totals['visitors'] * 100 : 0.0;

        return $totals;
    }

    /**
     * Une ligne par jour de la période : `daily_stats` pour les jours passés
     * (les jours manquants sont calculés et enregistrés au passage —
     * auto-réparation si la tâche planifiée n'a pas tourné), direct pour
     * aujourd'hui.
     *
     * @return array<string, array<string, float|int>>
     */
    private function rows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = CarbonImmutable::today();
        $rows = [];

        $lastPast = $to->lt($today) ? $to : $today->subDay();

        if ($from->lte($lastPast)) {
            $stored = DB::table('daily_stats')
                ->whereBetween('day', [$from->toDateString(), $lastPast->toDateString()])
                ->get()
                ->keyBy(fn (object $row): string => substr((string) $row->day, 0, 10));

            for ($day = $from; $day->lte($lastPast); $day = $day->addDay()) {
                $date = $day->toDateString();

                if (! $stored->has($date)) {
                    $this->aggregateDay($day);
                    $stored[$date] = DB::table('daily_stats')->where('day', $date)->first();
                }

                $rows[$date] = $this->normalize((array) $stored[$date]);
            }
        }

        if ($from->lte($today) && $to->gte($today)) {
            $rows[$today->toDateString()] = $this->compute($today);
        }

        return $rows;
    }

    /** @return array<string, float|int> */
    private function compute(CarbonImmutable $day): array
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $paid = DB::table('payments as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.status', PaymentStatus::Confirmed->value)
            ->whereBetween('p.confirmed_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(o.total), 0) as revenue, COALESCE(SUM(o.subtotal - o.discount_total), 0) as merchandise, COUNT(*) as paid_orders')
            ->first();

        return [
            'revenue' => (float) $paid->revenue,
            'merchandise' => (float) $paid->merchandise,
            'paid_orders' => (int) $paid->paid_orders,
            'placed_orders' => DB::table('orders')->whereBetween('created_at', [$start, $end])->count(),
            'visitors' => DB::table('daily_visitors')->where('day', $day->toDateString())->count(),
            'abandoned_carts' => DB::table('carts')->whereBetween('abandoned_at', [$start, $end])->count(),
        ];
    }

    /** @return array<string, float|int> */
    private function normalize(array $row): array
    {
        return [
            'revenue' => (float) $row['revenue'],
            'merchandise' => (float) $row['merchandise'],
            'paid_orders' => (int) $row['paid_orders'],
            'placed_orders' => (int) $row['placed_orders'],
            'visitors' => (int) $row['visitors'],
            'abandoned_carts' => (int) $row['abandoned_carts'],
        ];
    }
}
