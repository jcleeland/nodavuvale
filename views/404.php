<!-- pages/404.php -->
<?php

// Array of possible 404 messages
$messages = [
    [
        'title' => "Oops! You've Drifted Off Course",
        'description' => "Looks like the page you're searching for is lost at sea.",
        'button_text' => "Return to your Ancestral Home",
        'extra_title' => "Maybe this page hasn't been created yet?",
        'extra_description' => "Even if you haven't worked out how, your history belongs to you and is just waiting to be discovered.",
    ],
    [
        'title' => "Uh-oh, you've wandered too far!",
        'description' => "This page is as elusive as a coconut in a storm! But you can always return to the safety of home.",
        'button_text' => "Back to Home",
        'extra_title' => "Perhaps this path is meant to stay hidden",
        'extra_description' => "Don't worry, there's lots of other things you can find in other places",
    ],
    [
        'title' => "Oh no, you've moved away from the Village",
        'description' => "Looks like in your search for adventure you've left the village behind.",
        'button_text' => "Sevusevu",
        'extra_title' => "The village is calling you back",
        'extra_description' => "Don't worry, your home is always there to welcome you home when you respectfully ask. Just click the button to return.",
    ],
];

// Randomly select one of the messages
$random_message = $messages[array_rand($messages)];
?>
<section class="hero text-white py-20 bg-deep-green">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold"><?php echo $random_message['title']; ?></h2>
        <p class="mt-4 text-lg"><?php echo $random_message['description']; ?></p>
        <button class="mt-8 px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange">
            <a href="index.php"><?php echo $random_message['button_text']; ?></a>
        </button>
    </div>
</section>

<!-- Fun Content Section -->
<section class="container mx-auto py-12">
    <div class="p-6 bg-white shadow-lg rounded-lg text-center">
        <h3 class="text-2xl font-bold"><?php echo $random_message['extra_title']; ?></h3>
        <p class="mt-4"><?php echo $random_message['extra_description']; ?></p>
        <p class="mt-4">While you're here, why not explore some other parts of <i>Soli's Children</i>?</p>
        <div class="mt-6">
            <a href="?to=about/aboutnataleira" class="px-4 py-2 bg-ocean-blue text-white rounded-lg hover:bg-burnt-orange">About Nataleira</a>
            <a href="?to=about/aboutvirtualnataleira" class="ml-4 px-4 py-2 bg-warm-red text-white rounded-lg hover:bg-burnt-orange">Family Tree</a>
        </div>
    </div>
</section>

<?= $pagePath ?>
