<?php $ratingId='rating-'.$ratingContext.'-'.$ratingObjectId; ?>
<form class="star-rating-form" method="post" action="<?= e(url($ratingAction)) ?>">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($ratingObjectId) ?>">
  <fieldset><legend><?= e($ratingLegend) ?></legend>
    <div class="rating-choices" role="radiogroup" aria-label="Note sur cinq étoiles">
      <input type="radio" name="rating" value="0" id="<?= e($ratingId) ?>-0" <?= (int)($rating??0)===0?'checked':'' ?>><label class="rating-clear" for="<?= e($ratingId) ?>-0" title="Effacer la note">×</label>
      <?php for($star=5;$star>=1;$star--): ?><input type="radio" name="rating" value="<?= $star ?>" id="<?= e($ratingId) ?>-<?= $star ?>" <?= (int)($rating??0)===$star?'checked':'' ?>><label for="<?= e($ratingId) ?>-<?= $star ?>" title="<?= $star ?> sur 5">★</label><?php endfor; ?>
    </div>
    <button class="button secondary" type="submit">Enregistrer la note</button>
  </fieldset>
</form>
