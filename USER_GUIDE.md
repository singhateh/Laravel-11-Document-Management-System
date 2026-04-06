# StegoLock User Guide: Secure Document Steganography Made Simple

## Introduction

StegoLock is a secure document protection system that hides your sensitive files inside ordinary carrier images using advanced steganography techniques. Instead of relying on complex encryption alone, StegoLock embeds your documents within images in a way that's undetectable to the naked eye, providing an extra layer of security through obscurity.

**Core Purpose:** To allow users to securely hide and protect their documents without requiring technical expertise or repetitive uploads for each operation.

## Step-by-Step User Workflow

### 1. Initial Setup: Building Your Carrier Pool (One-Time Only)

Before you can protect documents, you need to upload carrier images to your personal pool. This is a one-time setup process:

1. **Navigate to the Carrier Pool section** in the application
2. **Upload your carrier images** - These should be high-quality PNG, JPEG, or BMP images
3. **Wait for automatic validation** - The system checks each image for quality and capacity
4. **Repeat until you have enough carriers** - The system will tell you your total available capacity

> 💡 **Tip:** Upload images with high resolution and varied content for best results. The system accepts common image formats and validates them automatically.

### 2. Protecting a Document

Once your carrier pool is ready, protecting a document is straightforward:

1. **Select the document** you want to protect from your files
2. **Choose your Master Key** - This is derived from your login session (no need to remember separate passwords)
3. **Optional: Enable System Carrier Fallback** - Check this box if you want the system to use backup carriers when your pool is low
4. **Click "Encode & Save"** - The system handles everything automatically:
   - Encrypts your document with strong AES-256 encryption
   - Splits the encrypted data across your carrier images
   - Embeds the data using undetectable steganographic techniques
   - Stores the protected carriers securely
5. **Receive confirmation** - You'll see how many carriers were used and get a success message

### 3. Retrieving a Protected Document

To access your protected document later:

1. **Navigate to your protected documents list**
2. **Find the document** you want to retrieve
3. **Click "Decode"** - The system automatically:
   - Locates the carrier images
   - Extracts the hidden data
   - Decrypts it using your Master Key
   - Verifies the document's integrity
4. **Download your original document** - It will be exactly as you uploaded it

### 4. Managing Your Carrier Pool

Your carrier pool is reusable and manageable:

- **View your carriers** - See all uploaded images with their status and capacity
- **Check validation status** - Green checkmarks indicate ready-to-use carriers
- **Monitor usage** - See which carriers are currently in use by active protected documents
- **Remove carriers** - Delete any carriers you no longer want (only if not currently in use)
- **Upload more carriers** - Add to your pool anytime to increase capacity

## Key Features Explained

### Carrier Pool System
Instead of uploading new images every time you want to protect a document, you upload images once to your personal pool. The system remembers which images are validated and ready to use, eliminating repetitive uploads.

### Smart Carrier Selection
The system intelligently selects which carriers to use based on:
- **Capacity** - Chooses images that can hold the most data first
- **Availability** - Only uses carriers that aren't currently protecting other documents
- **Quality** - Only uses pre-validated images that passed quality checks

### System Carrier Backup (New Feature)
New users or those with depleted pools can still protect documents using the system's built-in backup carriers:
- **Automatic fallback** - When enabled, the system uses backup carriers if your pool is insufficient
- **Pre-validated** - All system carriers are ready to use immediately
- **Privacy-preserving** - Your documents are still encrypted with your personal key
- **Transparent** - You'll see when system carriers are being used

### Capacity Planning Tools
Before protecting a document, you can:
- **Check requirements** - See exactly how much space your document needs
- **View available capacity** - Check how much space your carrier pool provides
- **Get recommendations** - The system tells you if you need more carriers and approximately how many
- **Use preflight check** - Verify everything will work before starting the encoding process

### Security Features
- **Military-grade encryption** - Your documents are encrypted with AES-256 before embedding
- **Undetectable embedding** - Changes to carrier images are imperceptible to human eyes
- **Integrity verification** - The system detects if a protected document has been tampered with
- **Access control** - Only you can access your protected documents (based on login)

## Simple Example: Protecting a Family Photo Album

Let's walk through a typical use case:

**Scenario:** You want to protect a ZIP file containing 50 family photos (total size: 45MB) before storing it in cloud storage.

**Before StegoLock:** You would need to upload 45 carrier images every time you wanted to protect this file, and if any failed validation, you'd have to start over.

**With StegoLock:**

1. **One-time setup:** You upload 20 high-quality vacation photos to your carrier pool (takes 2 minutes)
2. **Protection process:**
   - Select your family photos ZIP file
   - The system shows: "This document requires approximately 45MB of carrier capacity"
   - Your pool shows: "Available capacity: 52MB (20 carriers)"
   - Enable "Use System Carrier Backup" as a safety net
   - Click "Encode & Save"
3. **Automatic processing:**
   - System encrypts your ZIP file
   - Selects the 15 largest carriers from your pool (optimizing for efficiency)
   - Embeds the encrypted data across those carriers
   - Stores the protected carriers
4. **Result:** You get a confirmation: "Document protected using 15 carrier images"
5. **Retrieval:** Months later, you simply select the protected document and click "Decode" to get your original ZIP file back

**Benefits experienced:**
- Only uploaded carriers once (not 45 times)
- Process took seconds instead of minutes
- No failed validations requiring restarts
- Peace of mind knowing your family photos are securely hidden

## Best Practices for Users

1. **Build a diverse pool** - Use images with different subjects, lighting, and compositions
2. **Monitor your capacity** - Check your available space before protecting large documents
3. **Use descriptive names** - Name your carriers meaningfully to easily identify them later
4. **Enable system backup** - Especially when starting out or protecting important documents
5. **Regularly review your pool** - Remove unused carriers to make space for new ones
6. **Remember your login** - Your Master Key is tied to your account, so keep your credentials secure

## Troubleshooting Common Issues

**"Not enough capacity" message:**
- Solution: Upload more or larger carrier images to your pool
- Alternative: Enable system carrier backup for temporary assistance

**"Carrier is in use" when trying to remove:**
- Solution: Wait until the protected document using that carrier is decoded or deleted
- Alternative: Choose different carriers for your current operation

**Poor image quality warnings:**
- Solution: Use higher resolution images or images with more varied content
- Avoid: Very uniform images (like solid colors) or heavily compressed images

**Decoding fails:**
- Solution: Ensure you're logged into the same account used for encoding
- Alternative: Check if the carrier images have been modified or moved

## Conclusion

StegoLock transforms complex steganographic technology into a simple, user-friendly experience. By eliminating repetitive uploads, providing intelligent carrier selection, offering system backups, and maintaining strong security guarantees, it allows anyone to protect their sensitive documents with confidence—no technical expertise required.

Your carrier pool becomes a reusable resource that grows more valuable over time, turning what was once a tedious process into a simple, reliable workflow for document protection.