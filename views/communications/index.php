<?php

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Communications Hub</h2>
        <p class="mt-4 text-lg">Read news, join discussions, and stay in touch with the Soli diaspora.</p>
    </div>
</section>

<!-- Conditional Content Section Based on Login Status -->
<?php if ($is_logged_in) ?>

<!-- Logged-in Content Sections -->
<section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Discussions Section -->
        <div class="p-6 bg-white shadow-lg rounded-lg">
            <h3 class="text-2xl font-bold">News & Talk</h3>
            <p class="mt-2">Read news updates, join ongoing discussions and exchange ideas with the Soli diaspora.</p>
            <a href="?to=communications/discussions" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">News & talk</a>
        </div>

        <!-- Your Family Section -->
        <div class="p-6 bg-white shadow-lg rounded-lg">
            <h3 class="text-2xl font-bold">Our Family</h3>
            <p class="mt-2">It's amazing all the things our family does. Discover family members, skills, and achievements.</p>
            <a href="?to=communications/ourfamily" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Explore Your Family</a>
        </div>
    </div>
</section>
