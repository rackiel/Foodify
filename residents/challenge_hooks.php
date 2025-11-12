<?php
/**
 * Challenge Progress Hooks
 * Include this file in pages where user activities should trigger challenge progress updates
 * Call the appropriate function after successful actions
 */

// Ensure the progress update functions are available
if (!function_exists('updateChallengeProgress')) {
    if (file_exists(__DIR__ . '/update_challenge_progress.php')) {
        include_once __DIR__ . '/update_challenge_progress.php';
    }
}

/**
 * Hook: After creating a food donation
 */
function triggerDonationChallenge($conn, $user_id) {
    if (function_exists('updateChallengeProgress')) {
        updateChallengeProgress($conn, $user_id, 'donation');
        updateChallengeProgress($conn, $user_id, 'sustainability');
    }
}

/**
 * Hook: After posting a recipe
 */
function triggerRecipeChallenge($conn, $user_id) {
    if (function_exists('updateChallengeProgress')) {
        updateChallengeProgress($conn, $user_id, 'recipe');
    }
}

/**
 * Hook: After community engagement (comment, like, share)
 */
function triggerCommunityChallenge($conn, $user_id) {
    if (function_exists('updateChallengeProgress')) {
        updateChallengeProgress($conn, $user_id, 'community');
    }
}

/**
 * Hook: After using/managing ingredients (waste reduction)
 */
function triggerWasteReductionChallenge($conn, $user_id) {
    if (function_exists('updateChallengeProgress')) {
        updateChallengeProgress($conn, $user_id, 'waste_reduction');
        updateChallengeProgress($conn, $user_id, 'sustainability');
    }
}

/**
 * Hook: Update all challenges for a user
 */
function triggerAllChallenges($conn, $user_id) {
    if (function_exists('updateChallengeProgress')) {
        updateChallengeProgress($conn, $user_id);
    }
}
?>

