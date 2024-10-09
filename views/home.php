<?php

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Welcome to <i><?= $site_name ?></i></h2>
        <p class="mt-4 text-lg">Connecting our family and preserving our cultural heritage.</p>
        <!-- Button-styled anchor tag -->
        <a href="?to=about/aboutvirtualnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
            Our website
        </a>
        <a href="?to=about/aboutnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
            Our village
        </a>

    </div>
</section>

<!-- Conditional Content Section Based on Login Status -->
<?php if ($is_logged_in): ?>

    <!-- Logged-in Content Sections -->
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Family Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Our Diaspora</h3>
                <p class="mt-2">Explore your roots and connect with family members around the world.</p>
                <a href="?to=family/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Connect with Family</a>
            </div>

            <!-- Village Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Nataleira Life</h3>
                <p class="mt-2">Learn about the cultural traditions and the history of Nataleira village.</p>
                <a href="?to=village/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Discover Village Life</a>
            </div>

            <!-- Communications Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Getting Together</h3>
                <p class="mt-2">Read news, join discussions and stay in touch with the Soli diaspora.</p>
                <a href="?to=communications/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Get Involved</a>
            </div>
        </div>
    </section>

<?php else: ?>

    <!-- Public Information for Visitors -->
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-3 gap-8">
            <!-- About Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">About Nataleira</h3>
                <p class="mt-2">Learn about the rich history and culture of Nataleira.</p>
                <a href="?to=about/aboutnataleira.php" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">About Nataleira</a>
            </div>

            <!-- Login/Register Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">About this site</h3>
                <p class="mt-2">Find out more about this site, it's story and who it is for.</p>
                <a href="?to=about/aboutvirtualnataleira.php" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">About <i>Soli's Children</i></a>
            </div>            

            <!-- Login/Register Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Login or Register</h3>
                <p class="mt-2">Soli's Children can enter the village by logging in or registering.</p>
                <a href="?to=login" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Login</a> | 
                <a href="?to=register" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Register</a>
            </div>
        </div>
    </section>

<?php endif; ?>