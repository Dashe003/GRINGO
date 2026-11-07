<?php if (current_user()): ?></div><footer class="footer">GRINGO <span>·</span> Cultivez votre style, une pièce à la fois.</footer></main></div><?php else: ?></main><?php endif; ?>
<script src="<?= e(APP_BASE_URL) ?>/public/assets/app.js?v=<?= (int) filemtime(APP_ROOT.'/public/assets/app.js') ?>" defer></script>
</body>
</html>
