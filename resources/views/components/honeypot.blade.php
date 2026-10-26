{{-- Piège à robots : champ invisible pour une personne (hors écran, hors
     tabulation, ignoré des lecteurs d'écran) mais rempli par les robots qui
     complètent tous les champs. Rempli = envoi ignoré côté contrôleur. --}}
<div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
    <label for="website">Ne pas remplir</label>
    <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
</div>
