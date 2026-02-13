#!/bin/bash
set -e

# If AUTH_PASSWORD is set (plaintext), generate the bcrypt hash
if [ -n "$AUTH_PASSWORD" ]; then
    AUTH_PASSWORD_HASH=$(php -r "echo password_hash('$AUTH_PASSWORD', PASSWORD_DEFAULT);")
    export AUTH_PASSWORD_HASH
fi

# Write .env file for phpdotenv to read
echo "AUTH_PASSWORD_HASH=$AUTH_PASSWORD_HASH" > /var/www/html/.env

# Start Apache (default CMD from php:apache image)
exec apache2-foreground
