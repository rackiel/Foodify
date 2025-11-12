-- Sample Challenges Insert Queries
-- Run this after the challenges table has been created
-- Make sure to update created_by with a valid admin user_id from your user_accounts table

-- Sample Challenge 1: Weekly Food Donation Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Share the Love - Weekly Donation Challenge',
    'Help reduce food waste by donating at least 3 excess food items this week. Every donation counts towards building a stronger, more caring community!',
    'weekly',
    'donation',
    50,
    3,
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    'active',
    'Top 3 donors will receive a special Community Hero badge and 100 bonus points!',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 2: Monthly Waste Reduction Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Zero Waste Month',
    'Commit to reducing food waste by planning meals and using ingredients before they expire. Track your progress and help save the environment!',
    'monthly',
    'waste_reduction',
    100,
    20,
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),
    LAST_DAY(CURDATE()),
    'active',
    'Certificate of Achievement and feature in our monthly newsletter',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 3: Daily Recipe Sharing Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Recipe of the Day',
    'Share your favorite recipe with the community today! Help others discover new ways to use ingredients and reduce waste.',
    'daily',
    'recipe',
    20,
    1,
    CURDATE(),
    CURDATE(),
    'active',
    '50 bonus points for the most liked recipe',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 4: Community Engagement Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Community Builder',
    'Engage with fellow community members by commenting, liking, and sharing posts. Build connections and spread positivity!',
    'weekly',
    'community',
    30,
    10,
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    'active',
    'Recognition as Community Champion of the Week',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 5: Sustainability Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Green Living Initiative',
    'Adopt sustainable practices in food management: proper storage, meal planning, composting, and sharing excess. Make a difference!',
    'monthly',
    'sustainability',
    150,
    15,
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),
    LAST_DAY(CURDATE()),
    'active',
    'Eco-Warrior Badge and feature on our website',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 6: Special Event - Holiday Food Drive
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Holiday Food Drive 2025',
    'Join our special holiday food drive! Donate food items to help families in need during the festive season. Every contribution makes a difference!',
    'special',
    'donation',
    200,
    10,
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 30 DAY),
    'active',
    'Gold Contributor Medal, 500 bonus points, and recognition certificate',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 7: Draft Challenge (Not Yet Active)
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Upcoming: Spring Cleaning Challenge',
    'Prepare for spring by organizing your pantry, checking expiry dates, and sharing items you won\'t use. Coming soon!',
    'weekly',
    'waste_reduction',
    75,
    5,
    DATE_ADD(CURDATE(), INTERVAL 14 DAY),
    DATE_ADD(CURDATE(), INTERVAL 21 DAY),
    'draft',
    'Spring Champion Badge and 200 bonus points',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 8: Completed Challenge (For Reference)
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'October Food Sharing Success',
    'Thank you to all participants who made October amazing! We shared over 500 meals and reduced waste significantly.',
    'monthly',
    'donation',
    100,
    5,
    DATE_SUB(CURDATE(), INTERVAL 45 DAY),
    DATE_SUB(CURDATE(), INTERVAL 15 DAY),
    'completed',
    'All participants received special badges',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 9: Quick Daily Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'Today\'s Good Deed',
    'Perform one act of food kindness today - donate, share, or help someone with meal planning!',
    'daily',
    'community',
    15,
    1,
    CURDATE(),
    CURDATE(),
    'active',
    '25 bonus points for sharing your story',
    1  -- Replace with actual admin user_id
);

-- Sample Challenge 10: Beginner-Friendly Challenge
INSERT INTO challenges (
    title, 
    description, 
    challenge_type, 
    category, 
    points, 
    target_value, 
    start_date, 
    end_date, 
    status, 
    prize_description, 
    created_by
) VALUES (
    'First Steps Challenge',
    'New to Foodify? Get started by making your first food donation! It\'s easy and makes a big impact.',
    'weekly',
    'donation',
    25,
    1,
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    'active',
    'Welcome Badge and 50 bonus points',
    1  -- Replace with actual admin user_id
);

-- Note: Before running these queries, make sure to:
-- 1. Replace "created_by = 1" with a valid user_id from your user_accounts table
-- 2. Adjust dates if needed for testing purposes
-- 3. The challenges table should already exist (created automatically by admin-challenges.php)

-- Optional: Insert some sample participants (after challenges are created)
-- First, get actual challenge_id and user_id values from your database

/*
-- Example participant inserts (uncomment and modify with actual IDs):

INSERT INTO challenge_participants (challenge_id, user_id, progress, completed, points_earned)
VALUES 
(1, 2, 2, FALSE, 0),  -- User 2 is at 2/3 progress on challenge 1
(1, 3, 3, TRUE, 50),  -- User 3 completed challenge 1
(2, 2, 15, FALSE, 0), -- User 2 is at 15/20 on challenge 2
(3, 4, 1, TRUE, 20),  -- User 4 completed challenge 3
(4, 2, 7, FALSE, 0),  -- User 2 is at 7/10 on challenge 4
(5, 3, 10, FALSE, 0), -- User 3 is at 10/15 on challenge 5
(6, 4, 5, FALSE, 0);  -- User 4 is at 5/10 on challenge 6
*/

-- Check your inserted challenges
SELECT 
    challenge_id,
    title,
    challenge_type,
    category,
    points,
    target_value,
    start_date,
    end_date,
    status
FROM challenges
ORDER BY created_at DESC;

