# Environment Variables for Cloudflare R2

This document provides the environment variables needed to configure Cloudflare R2 storage for StegoLock.

## Required Variables

Add these variables to your `.env` file:

```env
# Cloudflare R2 Storage Configuration
R2_ACCESS_KEY_ID=your-r2-access-key-id
R2_SECRET_ACCESS_KEY=your-r2-secret-access-key
R2_BUCKET=your-bucket-name
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_URL=https://<account-id>.r2.cloudflarestorage.com/your-bucket-name
R2_USE_PATH_STYLE_ENDPOINT=true
```

## Optional Variables

These variables are optional but recommended for advanced configuration:

```env
# Advanced R2 Configuration
R2_PREFIX=stego
R2_VISIBILITY=private
R2_TEMP_URL_EXPIRY=60
R2_CACHE_CONTROL=max-age=3600
```

## Example Configuration

Here's a complete example with all variables filled in:

```env
# Cloudflare R2 Storage Configuration
R2_ACCESS_KEY_ID=AKIAEXAMPLE123456789
R2_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY
R2_BUCKET=stegolock-storage
R2_ENDPOINT=https://1234567890.r2.cloudflarestorage.com
R2_URL=https://1234567890.r2.cloudflarestorage.com/stegolock-storage
R2_USE_PATH_STYLE_ENDPOINT=true
R2_PREFIX=stego
R2_VISIBILITY=private
R2_TEMP_URL_EXPIRY=60
R2_CACHE_CONTROL=max-age=3600
```

## Updating .env.example

To make it easier for other developers to set up R2, add these variables to your `.env.example` file:

```env
# Cloudflare R2 Storage Configuration
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_URL=
R2_USE_PATH_STYLE_ENDPOINT=true
R2_PREFIX=stego
R2_VISIBILITY=private
R2_TEMP_URL_EXPIRY=60
R2_CACHE_CONTROL=max-age=3600
```

## Variable Definitions

### Required Variables

1. **R2_ACCESS_KEY_ID**: Your Cloudflare R2 access key ID
2. **R2_SECRET_ACCESS_KEY**: Your Cloudflare R2 secret access key
3. **R2_BUCKET**: The name of your R2 bucket
4. **R2_ENDPOINT**: The R2 endpoint URL (format: `https://<account-id>.r2.cloudflarestorage.com`)
5. **R2_URL**: The full URL to your R2 bucket (format: `https://<account-id>.r2.cloudflarestorage.com/<bucket-name>`)
6. **R2_USE_PATH_STYLE_ENDPOINT**: Whether to use path-style endpoints (should be `true` for R2)

### Optional Variables

1. **R2_PREFIX**: Optional prefix for all object keys (e.g., `stego/`)
2. **R2_VISIBILITY**: Default visibility for uploaded files (can be `private` or `public`)
3. **R2_TEMP_URL_EXPIRY**: Default temporary URL expiry in minutes (default: 60)
4. **R2_CACHE_CONTROL**: Default Cache-Control header for uploaded files (default: `max-age=3600`)

## How to Obtain R2 Credentials

1. Log in to your Cloudflare dashboard
2. Select your account
3. Click on "R2" in the left sidebar
4. Click "Manage R2 API Tokens"
5. Click "Create API Token"
6. Enter a name for your token
7. Under "Permissions", select:
   - Object Read & Write
   - Bucket List
   - Bucket Read
8. Under "Bucket Access", select "All buckets" or specify your stegolock bucket
9. Click "Create API Token"
10. Save the `Access Key ID` and `Secret Access Key` in your `.env` file

## Troubleshooting

### Common Issues

1. **Environment Variable Not Found**: Make sure all required variables are set in your `.env` file
2. **Invalid Credentials**: Verify that your R2 access key and secret key are correct
3. **Bucket Not Found**: Check that the bucket name matches exactly with your R2 bucket name
4. **Endpoint Error**: Ensure your R2 endpoint URL is in the correct format

### Verification

You can verify your R2 configuration using the test script provided in `docs/r2-test-script.md`.
