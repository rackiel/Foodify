-- Create recipes and tips table with social media features
CREATE TABLE IF NOT EXISTS recipes_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_type ENUM('recipe', 'tip') NOT NULL DEFAULT 'recipe',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500) NULL,
    ingredients TEXT NULL, -- JSON format for recipes
    instructions TEXT NULL, -- For recipes
    cooking_time INT NULL, -- in minutes
    difficulty_level ENUM('Easy', 'Medium', 'Hard') NULL,
    servings INT NULL,
    calories_per_serving INT NULL,
    tags TEXT NULL, -- JSON array of tags
    is_public TINYINT(1) DEFAULT 1,
    likes_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- Create likes table for posts
CREATE TABLE IF NOT EXISTS recipe_tip_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES recipes_tips(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (post_id, user_id)
);

-- Create shares table for posts
CREATE TABLE IF NOT EXISTS recipe_tip_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    share_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES recipes_tips(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- Create comments table for posts
CREATE TABLE IF NOT EXISTS recipe_tip_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    parent_comment_id INT NULL, -- For nested comments/replies
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES recipes_tips(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES recipe_tip_comments(id) ON DELETE CASCADE
);

-- Create saved posts table (bookmarks)
CREATE TABLE IF NOT EXISTS recipe_tip_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES recipes_tips(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (post_id, user_id)
);

-- Create indexes for better performance (only if they don't exist)
CREATE INDEX IF NOT EXISTS idx_recipes_tips_user_id ON recipes_tips(user_id);
CREATE INDEX IF NOT EXISTS idx_recipes_tips_post_type ON recipes_tips(post_type);
CREATE INDEX IF NOT EXISTS idx_recipes_tips_created_at ON recipes_tips(created_at);
CREATE INDEX IF NOT EXISTS idx_recipes_tips_is_public ON recipes_tips(is_public);
CREATE INDEX IF NOT EXISTS idx_recipe_tip_likes_post_id ON recipe_tip_likes(post_id);
CREATE INDEX IF NOT EXISTS idx_recipe_tip_likes_user_id ON recipe_tip_likes(user_id);
CREATE INDEX IF NOT EXISTS idx_recipe_tip_shares_post_id ON recipe_tip_shares(post_id);
CREATE INDEX IF NOT EXISTS idx_recipe_tip_comments_post_id ON recipe_tip_comments(post_id);
