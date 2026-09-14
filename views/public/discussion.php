<?php
$host = $_SERVER['SERVER_NAME'] ?? '';
$renderedArticle = (new PublicArticleHtml((int) $publicArticle['id'], $host))->render($publicArticle['content']);
$articleImages = $publicDiscussions->images($publicArticle, $host);
?>
<section class="hero text-white py-20" aria-labelledby="public-article-heading">
    <div class="container hero-content">
        <h1 id="public-article-heading" class="text-4xl font-bold break-words"><?= htmlspecialchars(PublicDiscussions::title($publicArticle), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-4 text-lg">A story shared by our family.</p>
    </div>
</section>
<main class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <nav aria-label="Article navigation" class="mb-8">
        <a class="text-ocean-blue hover:text-burnt-orange underline" href="index.php#public-stories-heading">Back to family stories</a>
    </nav>
    <article class="p-6 sm:p-8 bg-white shadow-lg rounded-lg">
        <div class="public-article-content"><?= $renderedArticle['html'] ?></div>
        <?php foreach (array_diff($articleImages, $renderedArticle['images']) as $path): ?>
            <?php if (PublicDiscussions::imageFile($path, dirname(__DIR__, 2)) === null) { continue; } ?>
            <figure class="mt-8">
                <img class="public-article-image" loading="lazy" alt="Image accompanying this article" src="<?= htmlspecialchars(PublicDiscussions::imageUrl((int) $publicArticle['id'], $path), ENT_QUOTES, 'UTF-8') ?>">
            </figure>
        <?php endforeach; ?>
    </article>
</main>
