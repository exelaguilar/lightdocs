<article class="docs-article search-page">
  <header class="article-header"><div class="article-kicker"><span>Documentation</span></div><h1>Search</h1><p class="lead">Find pages, sections, commands, and concepts across the documentation.</p></header>
  <form class="search-page-form" action="/search" method="get"><span class="search-icon" aria-hidden="true"></span><input type="search" name="q" value="<?= $e($query) ?>" placeholder="What are you looking for?" autofocus><button class="button" type="submit">Search</button></form>
  <?php if ($query !== ''): ?><p class="search-count"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= $e($query) ?>”</p><?php endif; ?>
  <div class="search-page-results">
  <?php foreach ($results as $result): ?><a href="<?= $e($result['url']) ?>"><span class="result-arrow" aria-hidden="true">→</span><strong><?= $e($result['title']) ?></strong><span><?= $e($result['description']) ?></span></a><?php endforeach; ?>
  <?php if ($query !== '' && !$results): ?><div class="empty-state"><span class="search-orb" aria-hidden="true"></span><h2>No results found</h2><p>Try fewer words or a broader phrase.</p></div><?php endif; ?>
  </div>
</article>
