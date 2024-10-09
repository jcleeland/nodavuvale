<?php
// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);

$family_members=[];
?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Your Family</h2>
        <p class="mt-4 text-lg">Celebrate the individuals in our family, share skills, and collaborate on projects.</p>
    </div>
</section>

<!-- Conditional Content Section Based on Login Status -->
<?php if ($is_logged_in): ?>

    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Family Member Highlight -->
            <?php foreach ($family_members as $member): ?>
                <div class="p-6 bg-white shadow-lg rounded-lg">
                    <h3 class="text-2xl font-bold"><?= $member['first_name'] . " " . $member['last_name'] ?></h3>
                    <p class="mt-2"><?= $member['skills'] ?></p>
                    <a href="?to=profile&id=<?= $member['id'] ?>" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">View Profile</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

<?php else: ?>
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <p class="text-center text-lg">Please <a href="?to=login" class="text-ocean-blue hover:text-burnt-orange">log in</a> to explore the family and contribute your skills.</p>
    </section>
<?php endif; ?>
