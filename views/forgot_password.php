<?php
/** For existing users who have forgotten their password
 * 
 * views/forgot_password.php
 * 
 * This script asks them to provide their login (email) address
 * and then will use ajax and the method 'password_reset' to 
 * generate a new random password and send it to them via email.
 */

?>
<section class="container mx-auto py-12">
    <div class="max-w-md mx-auto bg-white p-8 shadow-lg rounded-lg">
        <h2 class="text-2xl font-bold mb-6 text-center">Forgot <i>Soli's Children</i> Password</h2>
        <p class="text-center text-gray-600 mb-6">Enter the email address you use to login below and we will send you a new password.</p>
        <!-- Display error message if login fails or user is unapproved -->
        <div class="mb-4 text-green-500 text-center hidden" id="emailSent">
            If the login email you provide is registered, you will shortly receive an email with a new password.
        </div>
        <div class="mb-4">
            <label for="email" class="block text-gray-700">Email</label>
            <input type="email" id="requested_user_email" name="requested_user_email" class="w-full px-4 py-2 border rounded-lg" required>
        </div>
        <div class="text-center">
            <button type="button" id="resetPasswordButton" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg" onclick="userResetPassword()">
                Reset Password
            </button>
        </div>
        <div id="response" class="text-center text-red-600"></div>
    </div>
</section>