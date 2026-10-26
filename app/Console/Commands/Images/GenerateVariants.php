<?php

namespace App\Console\Commands\Images;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductImage;
use App\Models\Content\HeroSlide;
use App\Models\Content\Look;
use App\Models\Content\Post;
use App\Services\ImageVariantGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('images:generate-variants')]
#[Description('Régénère les variantes redimensionnées (thumb/medium/large/xlarge) de toutes les images du site : à relancer après l\'ajout d\'une taille.')]
class GenerateVariants extends Command
{
    public function handle(ImageVariantGenerator $generator): int
    {
        $paths = collect()
            ->merge(ProductImage::query()->pluck('path'))
            ->merge(HeroSlide::query()->pluck('image_path'))
            ->merge(HeroSlide::query()->pluck('mobile_image_path'))
            ->merge(Category::query()->pluck('image_path'))
            ->merge(Collection::query()->pluck('image_path'))
            ->merge(Post::query()->pluck('cover_image_path'))
            ->merge(Look::query()->pluck('image_path'))
            ->filter()
            ->unique()
            ->values();

        $this->withProgressBar($paths, fn (string $path) => $generator->generate($path));

        $this->newLine(2);
        $this->info("Variantes générées pour {$paths->count()} image(s).");

        return self::SUCCESS;
    }
}
