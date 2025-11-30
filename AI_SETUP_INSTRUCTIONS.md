# AI Recipe Suggestions Setup Instructions

## Overview
The Recipe Suggestions feature uses OpenAI's API to generate intelligent recipe suggestions based on available ingredients. When a specific ingredient is selected, the system uses NLP algorithms to extract Filipino dishes from a PDF file instead of AI.

## Configuration Methods

### Method 1: Config File (Recommended for Development)
1. Open `config/ai_config.php`
2. Replace `'your-openai-api-key'` with your actual OpenAI API key
3. Save the file

### Method 2: Environment Variable (Recommended for Production)
Set the environment variable `OPENAI_API_KEY` on your server:
- **Windows (XAMPP)**: Add to system environment variables or set in `httpd.conf`
- **Linux**: Add to `/etc/environment` or `.bashrc`
- **cPanel**: Use the "Environment Variables" feature

### Method 3: Direct Configuration (Not Recommended)
Edit `residents/suggested_recipes.php` line ~15 and replace the API key directly.

## Getting Your OpenAI API Key

1. Go to [https://platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Sign up or log in to your OpenAI account
3. Click "Create new secret key"
4. Copy the key (it starts with `sk-`)
5. Paste it into your chosen configuration method above

## Important Notes

- **API Costs**: OpenAI charges per API call. Monitor your usage at [https://platform.openai.com/usage](https://platform.openai.com/usage)
- **Security**: Never commit API keys to version control. The `config/ai_config.php` file is already in `.gitignore`
- **Focus Ingredient**: When a user selects a specific ingredient (e.g., "Malunggay Leaves"), the system uses PDF parsing instead of AI to get actual Filipino dishes from the PDF file
- **Fallback**: If API key is not configured, the system will use mock data for generic suggestions (when no focus ingredient is specified)

## Testing

After configuring your API key:
1. Go to `residents/suggested_recipes.php`
2. Add some ingredients
3. Click "Get AI Suggestions"
4. You should see real AI-generated recipe suggestions

## Troubleshooting

- **"AI Configuration Required" notice**: Your API key is not configured correctly
- **API errors in logs**: Check your API key is valid and you have credits in your OpenAI account
- **No recipes returned**: Check PHP error logs for detailed error messages

