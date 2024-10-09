<?php
$individual_id = $_POST['individual_id'];
    $first_names = $_POST['first_names'];
    $aka_names = $_POST['aka_names'];
    $last_name = $_POST['last_name'];
    $birth_prefix = $_POST['birth_prefix'];
    $birth_year = isset($_POST['birth_year']) ? $_POST['birth_year'] : null;
    $birth_month = isset($_POST['birth_month']) ? $_POST['birth_month'] : null;
    $birth_date = isset($_POST['birth_date']) && !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $death_prefix = isset($_POST['death_prefix']) ? $_POST['death_prefix'] : null;
    $death_year = isset($_POST['death_year']) ? $_POST['death_year'] : null;
    $death_month = isset($_POST['death_month']) ? $_POST['death_month'] : null;
    $death_date = isset($_POST['death_date']) && !empty($_POST['death_date']) ? $_POST['death_date'] : null;
    $gender = $_POST['gender'];

    // Update the individual in the database
    $db->query(
        "UPDATE individuals SET first_names = ?, aka_names = ?, last_name = ?, birth_prefix = ?, birth_year = ?, birth_month = ?, birth_date = ?, death_prefix = ?, death_year = ?, death_month = ?, death_date = ?, gender = ? WHERE id = ?",
        [$first_names, $aka_names, $last_name, $birth_prefix, $birth_year, $birth_month, $birth_date, $death_prefix, $death_year, $death_month, $death_date, $gender, $individual_id]
    );

    //echo "<pre>"; print_r($_POST); echo "</pre>";
    //echo "<pre>"; print_r([$first_names, $aka_names, $last_name, $birth_prefix, $birth_year, $birth_month, $birth_date, $death_prefix, $death_year, $death_month, $death_date, $gender, $individual_id]); echo "</pre>";
    //die();
