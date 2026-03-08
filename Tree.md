# File Tree: Test_Caps

**Generated:** 3/6/2026, 10:34:18 PM
**Root Path:** `c:\Users\USER\Desktop\Test\Test_Caps`

```
├── 📁 app
│   ├── 📁 Console
│   │   └── 📁 Commands
│   │       └── 🐘 TestStegoEncoding.php
│   ├── 📁 Helpers
│   │   ├── 🐘 FolderHelper.php
│   │   └── 🐘 helper.php
│   ├── 📁 Http
│   │   ├── 📁 Concerns
│   │   │   └── 🐘 HasStegoEncoding.php
│   │   ├── 📁 Controllers
│   │   │   ├── 📁 Api
│   │   │   │   ├── 🐘 AuthController.php
│   │   │   │   ├── 🐘 DashboardController.php
│   │   │   │   ├── 🐘 DocumentController.php
│   │   │   │   └── 🐘 StegoDocumentController.php
│   │   │   ├── 📁 Auth
│   │   │   │   ├── 🐘 AuthenticatedSessionController.php
│   │   │   │   ├── 🐘 ConfirmablePasswordController.php
│   │   │   │   ├── 🐘 EmailVerificationNotificationController.php
│   │   │   │   ├── 🐘 EmailVerificationPromptController.php
│   │   │   │   ├── 🐘 NewPasswordController.php
│   │   │   │   ├── 🐘 PasswordController.php
│   │   │   │   ├── 🐘 PasswordResetLinkController.php
│   │   │   │   ├── 🐘 RegisteredUserController.php
│   │   │   │   └── 🐘 VerifyEmailController.php
│   │   │   ├── 🐘 CategoryController.php
│   │   │   ├── 🐘 CommentController.php
│   │   │   ├── 🐘 Controller.php
│   │   │   ├── 🐘 DocumentController.php
│   │   │   ├── 🐘 FileRequestController.php
│   │   │   ├── 🐘 FolderController.php
│   │   │   ├── 🐘 HomeController.php
│   │   │   ├── 🐘 NotificationController.php
│   │   │   ├── 🐘 ProfileController.php
│   │   │   ├── 🐘 SearchController.php
│   │   │   ├── 🐘 ShareDocumentController.php
│   │   │   ├── 🐘 StegoWebController.php
│   │   │   ├── 🐘 TagController.php
│   │   │   └── 🐘 UserController.php
│   │   ├── 📁 Middleware
│   │   │   └── 🐘 HandleInertiaRequests.php
│   │   └── 📁 Requests
│   │       ├── 📁 Auth
│   │       │   └── 🐘 LoginRequest.php
│   │       ├── 🐘 ProfileUpdateRequest.php
│   │       ├── 🐘 StoreCategoryRequest.php
│   │       ├── 🐘 StoreDocumentRequest.php
│   │       ├── 🐘 StoreFileRequestRequest.php
│   │       ├── 🐘 StoreFolderRequest.php
│   │       ├── 🐘 StoreNotificationRequest.php
│   │       ├── 🐘 StoreShareDocumentRequest.php
│   │       ├── 🐘 StoreTagRequest.php
│   │       ├── 🐘 UpdateCategoryRequest.php
│   │       ├── 🐘 UpdateDocumentRequest.php
│   │       ├── 🐘 UpdateFileRequestRequest.php
│   │       ├── 🐘 UpdateFolderRequest.php
│   │       ├── 🐘 UpdateNotificationRequest.php
│   │       ├── 🐘 UpdateShareDocumentRequest.php
│   │       └── 🐘 UpdateTagRequest.php
│   ├── 📁 Jobs
│   │   ├── 🐘 SendDocumentJob.php
│   │   └── 🐘 SendFileRequestEmail.php
│   ├── 📁 Mail
│   │   ├── 🐘 FileRequestNotification.php
│   │   └── 🐘 SendDocumentEmail.php
│   ├── 📁 Models
│   │   ├── 🐘 AccessLog.php
│   │   ├── 🐘 Category.php
│   │   ├── 🐘 Comment.php
│   │   ├── 🐘 Document.php
│   │   ├── 🐘 FileRequest.php
│   │   ├── 🐘 Folder.php
│   │   ├── 🐘 Notification.php
│   │   ├── 🐘 RoleGrant.php
│   │   ├── 🐘 ShareDocument.php
│   │   ├── 🐘 StegoCarrier.php
│   │   ├── 🐘 StegoDocument.php
│   │   ├── 🐘 StegoDocumentGrant.php
│   │   ├── 🐘 StegoSegment.php
│   │   ├── 🐘 Tag.php
│   │   └── 🐘 User.php
│   ├── 📁 Policies
│   │   ├── 🐘 CategoryPolicy.php
│   │   ├── 🐘 DocumentPolicy.php
│   │   ├── 🐘 FileRequestPolicy.php
│   │   ├── 🐘 FolderPolicy.php
│   │   ├── 🐘 NotificationPolicy.php
│   │   ├── 🐘 ShareDocumentPolicy.php
│   │   └── 🐘 TagPolicy.php
│   ├── 📁 Providers
│   │   └── 🐘 AppServiceProvider.php
│   └── 📁 Services
│       ├── 📁 Stego
│       │   ├── 🐘 CloudStorageService.php
│       │   ├── 🐘 CryptoService.php
│       │   ├── 🐘 PersistenceService.php
│       │   ├── 🐘 SegmentationService.php
│       │   ├── 🐘 StegoDocumentService.php
│       │   └── 🐘 StegoService.php
│       ├── 🐘 DocumentService.php
│       └── 🐘 FolderService.php
├── 📁 bootstrap
│   ├── 🐘 app.php
│   └── 🐘 providers.php
├── 📁 config
│   ├── 🐘 app.php
│   ├── 🐘 auth.php
│   ├── 🐘 cache.php
│   ├── 🐘 database.php
│   ├── 🐘 filesystems.php
│   ├── 🐘 logging.php
│   ├── 🐘 mail.php
│   ├── 🐘 queue.php
│   ├── 🐘 services.php
│   ├── 🐘 session.php
│   └── 🐘 stegolock.php
├── 📁 database
│   ├── 📁 factories
│   │   ├── 🐘 CategoryFactory.php
│   │   ├── 🐘 DocumentFactory.php
│   │   ├── 🐘 FileRequestFactory.php
│   │   ├── 🐘 FolderFactory.php
│   │   ├── 🐘 NotificationFactory.php
│   │   ├── 🐘 ShareDocumentFactory.php
│   │   ├── 🐘 StegoDocumentFactory.php
│   │   ├── 🐘 StegoDocumentGrantFactory.php
│   │   ├── 🐘 TagFactory.php
│   │   └── 🐘 UserFactory.php
│   ├── 📁 migrations
│   │   ├── 🐘 0001_01_01_000000_create_users_table.php
│   │   ├── 🐘 0001_01_01_000001_create_cache_table.php
│   │   ├── 🐘 0001_01_01_000002_create_jobs_table.php
│   │   ├── 🐘 2024_01_01_000005_create_role_grants_table.php
│   │   ├── 🐘 2024_01_01_000006_create_access_logs_table.php
│   │   ├── 🐘 2024_03_22_175904_create_folders_table.php
│   │   ├── 🐘 2024_03_22_175905_create_documents_table.php
│   │   ├── 🐘 2024_03_23_052817_create_categories_table.php
│   │   ├── 🐘 2024_03_23_052818_create_tags_table.php
│   │   ├── 🐘 2024_03_23_054023_create_folder_tag_table.php
│   │   ├── 🐘 2024_03_23_054125_create_document_tag_table.php
│   │   ├── 🐘 2024_03_24_050023_create_notifications_table.php
│   │   ├── 🐘 2024_03_26_054042_category_folder.php
│   │   ├── 🐘 2024_03_26_055235_create_category_tag_table.php
│   │   ├── 🐘 2024_03_26_142021_create_share_documents_table.php
│   │   ├── 🐘 2024_03_27_012855_create_file_requests_table.php
│   │   ├── 🐘 2026_02_15_131842_add_position_to_folders_table.php
│   │   ├── 🐘 2026_02_15_140000_create_comments_table.php
│   │   ├── 🐘 2026_02_15_141000_add_avatar_to_users_table.php
│   │   ├── 🐘 2026_02_20_000001_add_username_role_to_users_table.php
│   │   ├── 🐘 2026_02_20_000002_create_stego_carriers_table.php
│   │   ├── 🐘 2026_02_20_000003_create_stego_segments_table.php
│   │   ├── 🐘 2026_02_20_000004_create_stego_documents_table.php
│   │   ├── 🐘 2026_02_25_045401_create_personal_access_tokens_table.php
│   │   ├── 🐘 2026_02_25_045900_add_user_to_role_enum_in_users_table.php
│   │   ├── 🐘 2026_03_03_000001_add_encryption_columns_to_documents_table.php
│   │   ├── 🐘 2026_03_05_000001_add_mkd_salt_to_users_table.php
│   │   ├── 🐘 2026_03_05_000001_rename_notifications_polymorphic_columns.php
│   │   ├── 🐘 2026_03_05_000002_add_psnr_to_stego_carriers_table.php
│   │   ├── 🐘 2026_03_05_000003_add_unique_index_to_document_tag_table.php
│   │   ├── 🐘 2026_03_05_000004_drop_s3_url_from_stego_tables.php
│   │   ├── 🐘 2026_03_05_000005_rename_stego_documents_enc_columns.php
│   │   ├── 🐘 2026_03_05_000005_update_share_documents_table.php
│   │   ├── 🐘 2026_03_05_000006_drop_contact_from_documents.php
│   │   ├── 🐘 2026_03_05_000006_drop_timestamps_from_access_logs.php
│   │   ├── 🐘 2026_03_05_000007_rename_columns_in_documents_table.php
│   │   ├── 🐘 2026_03_05_000008_change_segment_index_to_smallint.php
│   │   ├── 🐘 2026_03_05_000009_create_taggables_table.php
│   │   ├── 🐘 2026_03_06_000001_add_slug_description_to_categories_table.php
│   │   └── 🐘 2026_03_06_000001_create_stego_document_grants_table.php
│   ├── 📁 seeders
│   │   ├── 🐘 CategorySeeder.php
│   │   ├── 🐘 DatabaseSeeder.php
│   │   ├── 🐘 DocumentSeeder.php
│   │   ├── 🐘 FileRequestSeeder.php
│   │   ├── 🐘 FolderSeeder.php
│   │   ├── 🐘 NotificationSeeder.php
│   │   ├── 🐘 ShareDocumentSeeder.php
│   │   ├── 🐘 TagSeeder.php
│   │   └── 🐘 UserSeeder.php
│   └── ⚙️ .gitignore
├── 📁 public
│   ├── 📁 custom-css
│   │   └── 🎨 documents12.css
│   ├── 📁 custom-js
│   │   ├── 📄 addFolder.js
│   │   ├── 📄 addUrl.js
│   │   ├── 📄 documents1001.js
│   │   ├── 📄 request.js
│   │   ├── 📄 share.js
│   │   └── 📄 uploadFolder.js
│   ├── 📁 documents
│   │   ├── 📁 New Folder Uploaded
│   │   │   ├── 📁 Team Members
│   │   │   │   ├── 🖼️ Abdoulie Ceesay.png
│   │   │   │   ├── 🖼️ Almamo Ceesay_.png
│   │   │   │   ├── 🖼️ Dawda Kujabi.png
│   │   │   │   ├── 🖼️ FATOUMATTA KANYI.png
│   │   │   │   ├── 🖼️ MARYAM SANKANU.png
│   │   │   │   ├── 🖼️ Muhammad Diabassey.png
│   │   │   │   └── 🖼️ Sally Sanyang.png
│   │   │   ├── 🎬 1.mp4
│   │   │   ├── 🎬 2.mp4
│   │   │   ├── 🎬 3.mp4
│   │   │   ├── 🎬 5.mp4
│   │   │   ├── 🎬 6.mp4
│   │   │   ├── 🎬 7.mp4
│   │   │   ├── 📕 CERTIFICAT.pdf
│   │   │   ├── 📄 Comparion video (Style 2).pptx
│   │   │   ├── 📄 Comparison Video 195 slots.pptx
│   │   │   ├── 📄 Comparison Video 205 slots.pptx
│   │   │   ├── 📄 Comparison Video 68 slots.pptx
│   │   │   ├── 📄 Comparison Video.pptx
│   │   │   └── 📄 Updated comparison template.pptx
│   │   ├── 📁 Play.ht - Welcome to a remarkable journey where
│   │   │   ├── 📕 COLLEGE.pdf
│   │   │   ├── 📕 Ministry of Heall.pdf
│   │   │   ├── 📕 The West African Original.pdf
│   │   │   ├── 📕 The West African.pdf
│   │   │   ├── 📕 West African Examination Council.pdf
│   │   │   └── 📕 f Graduation.pdf
│   │   ├── 📁 Team Members
│   │   │   └── 📁 Play.ht - Welcome to a remarkable journey where
│   │   │       ├── 🎵 01 - Welcome to a remarkable j 1.wav
│   │   │       ├── 🎵 02 - Our adventure begins as w 1.wav
│   │   │       ├── 🎵 03 - After building more wells 1.wav
│   │   │       ├── 🎵 04 - The harsh reality of the 1.wav
│   │   │       ├── 🎵 05 - This situation is heartbr 1.wav
│   │   │       ├── 🎵 06 - Now let-s delve into the 1.wav
│   │   │       ├── 🎵 07 - The impact of these wells 1.wav
│   │   │       ├── 🎵 08 - Solving this water crisis 1.wav
│   │   │       ├── 🎵 09 - The journey continues as 1.wav
│   │   │       ├── 🎵 10 - The mission extends to gi 1.wav
│   │   │       ├── 🎵 11 - But the team encounters c 1.wav
│   │   │       ├── 🎵 12 - A safer and more accessib 1.wav
│   │   │       ├── 🎵 13 - The team encounters a dan 1.wav
│   │   │       ├── 🎵 14 - The journey takes us to Z 2.wav
│   │   │       ├── 🎵 15 - The mission extends to ot 1.wav
│   │   │       ├── 🎵 16 - We-ll explore the unique 1.wav
│   │   │       ├── 🎵 17 - As we reflect on this inc 1.wav
│   │   │       ├── 🎵 18 - But this is just the begi 2.wav
│   │   │       └── 🎵 19 - In conclusion this video 2.wav
│   │   └── 📁 Test
│   │       ├── 📕 Arduino Cheat Sheet.pdf
│   │       ├── 📕 Arduino.pdf
│   │       ├── 📕 Case Study.pdf
│   │       ├── 📕 Intelligent-Auto-Dim-Block-Diagram.pdf
│   │       └── 📕 Webinar #3 Reflection.pdf
│   ├── 📁 img
│   │   ├── 🖼️ add-group.png
│   │   ├── 🖼️ assignment.png
│   │   ├── 🖼️ avi.png
│   │   ├── 🖼️ bg-audio.jpg
│   │   ├── 🖼️ certificate.png
│   │   ├── 🖼️ check-mark.png
│   │   ├── 🖼️ class.png
│   │   ├── 🖼️ contact-book.png
│   │   ├── 🖼️ contacts.png
│   │   ├── 🖼️ content.png
│   │   ├── 🖼️ course-1.jpg
│   │   ├── 🖼️ course.png
│   │   ├── 🖼️ document-management-background.jpg
│   │   ├── 🖼️ document.png
│   │   ├── 🖼️ docx.png
│   │   ├── 🖼️ empty-document.png
│   │   ├── 🖼️ fly.png
│   │   ├── 🖼️ folder.png
│   │   ├── 🖼️ google-docs.png
│   │   ├── 🖼️ group.png
│   │   ├── 🖼️ information.png
│   │   ├── 🖼️ jpeg.png
│   │   ├── 🖼️ lesson.png
│   │   ├── 🖼️ link.png
│   │   ├── 🖼️ links.png
│   │   ├── 🖼️ live.png
│   │   ├── 🖼️ mkv.png
│   │   ├── 🖼️ mov (2).png
│   │   ├── 🖼️ mov.png
│   │   ├── 🖼️ mp3.png
│   │   ├── 🖼️ mp4.png
│   │   ├── 🖼️ no-group.png
│   │   ├── 🖼️ no-image.jpg
│   │   ├── 🖼️ not-found.webp
│   │   ├── 🖼️ pdf.png
│   │   ├── 🖼️ ppt.png
│   │   ├── 🖼️ pptx.png
│   │   ├── 🖼️ profile.JPG
│   │   ├── 🖼️ project.png
│   │   ├── 🖼️ quiz.png
│   │   ├── 🖼️ students.png
│   │   ├── 🖼️ template.png
│   │   ├── 🖼️ topics.png
│   │   ├── 🖼️ trade.png
│   │   ├── 🖼️ virtual-class.png
│   │   ├── 🖼️ waiting.png
│   │   ├── 🖼️ wav.png
│   │   ├── 🖼️ wmv.png
│   │   ├── 🖼️ xls.png
│   │   ├── 🖼️ xlsx.png
│   │   └── 🖼️ youtube.png
│   ├── ⚙️ .htaccess
│   ├── 📄 favicon.ico
│   ├── 🐘 index.php
│   └── 📄 robots.txt
├── 📁 python
│   └── 🐍 stego_lsb.py
├── 📁 resources
│   ├── 📁 css
│   │   └── 🎨 app.css
│   ├── 📁 js
│   │   ├── 📁 Components
│   │   │   ├── 📄 AdvancedSearchModal.tsx
│   │   │   ├── 📄 ApplicationLogo.tsx
│   │   │   ├── 📄 Avatar.tsx
│   │   │   ├── 📄 Checkbox.tsx
│   │   │   ├── 📄 Comments.tsx
│   │   │   ├── 📄 DangerButton.tsx
│   │   │   ├── 📄 DocumentPreview.tsx
│   │   │   ├── 📄 DragDropUploadModal.tsx
│   │   │   ├── 📄 Dropdown.tsx
│   │   │   ├── 📄 InputError.tsx
│   │   │   ├── 📄 InputLabel.tsx
│   │   │   ├── 📄 Modal.tsx
│   │   │   ├── 📄 NavLink.tsx
│   │   │   ├── 📄 PrimaryButton.tsx
│   │   │   ├── 📄 ResponsiveNavLink.tsx
│   │   │   ├── 📄 SecondaryButton.tsx
│   │   │   ├── 📄 SimpleUploadModal.tsx
│   │   │   ├── 📄 TextInput.tsx
│   │   │   └── 📄 UploadModal.tsx
│   │   ├── 📁 Layouts
│   │   │   ├── 📄 AuthenticatedLayout.tsx
│   │   │   └── 📄 GuestLayout.tsx
│   │   ├── 📁 Pages
│   │   │   ├── 📁 Auth
│   │   │   │   ├── 📄 ConfirmPassword.tsx
│   │   │   │   ├── 📄 ForgotPassword.tsx
│   │   │   │   ├── 📄 Login.tsx
│   │   │   │   ├── 📄 Register.tsx
│   │   │   │   ├── 📄 ResetPassword.tsx
│   │   │   │   └── 📄 VerifyEmail.tsx
│   │   │   ├── 📁 Categories
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📁 Contacts
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📁 Documents
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📁 Folders
│   │   │   │   ├── 📄 Create.tsx
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📁 Profile
│   │   │   │   ├── 📁 Partials
│   │   │   │   │   ├── 📄 DeleteUserForm.tsx
│   │   │   │   │   ├── 📄 UpdatePasswordForm.tsx
│   │   │   │   │   └── 📄 UpdateProfileInformationForm.tsx
│   │   │   │   └── 📄 Edit.tsx
│   │   │   ├── 📁 Projects
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📁 Search
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📁 Stego
│   │   │   │   ├── 📄 Decode.tsx
│   │   │   │   ├── 📄 Encode.tsx
│   │   │   │   ├── 📄 Index.tsx
│   │   │   │   └── 📄 Tokens.tsx
│   │   │   ├── 📁 Tags
│   │   │   │   └── 📄 Index.tsx
│   │   │   ├── 📄 Dashboard.tsx
│   │   │   ├── 📄 Home.tsx
│   │   │   └── 📄 Welcome.tsx
│   │   ├── 📁 stegolock-spa
│   │   │   ├── 📁 components
│   │   │   │   └── 📄 SpaLayout.tsx
│   │   │   ├── 📁 hooks
│   │   │   │   └── 📄 useAuth.tsx
│   │   │   ├── 📁 pages
│   │   │   │   ├── 📄 Dashboard.tsx
│   │   │   │   ├── 📄 Decode.tsx
│   │   │   │   ├── 📄 Encode.tsx
│   │   │   │   ├── 📄 Login.tsx
│   │   │   │   ├── 📄 StegoIndex.tsx
│   │   │   │   └── 📄 Tokens.tsx
│   │   │   ├── 📄 App.tsx
│   │   │   └── 📄 main.tsx
│   │   ├── 📁 types
│   │   │   ├── 📄 global.d.ts
│   │   │   ├── 📄 index.d.ts
│   │   │   └── 📄 vite-env.d.ts
│   │   ├── 📄 app.tsx
│   │   └── 📄 bootstrap.ts
│   └── 📁 views
│       ├── 📁 components
│       │   ├── 🐘 avatar.blade.php
│       │   ├── 🐘 modal.blade.php
│       │   └── 🐘 notFound.blade.php
│       ├── 📁 contacts
│       ├── 📁 documents
│       │   ├── 📁 uploads
│       │   │   ├── 🐘 addUrl.blade.php
│       │   │   ├── 🐘 requestDocument.blade.php
│       │   │   ├── 🐘 shareDocument.blade.php
│       │   │   └── 🐘 uploadFolder.blade.php
│       │   └── 🐘 previewDocument.blade.php
│       ├── 📁 emails
│       │   ├── 🐘 file_request_notification.blade.php
│       │   └── 🐘 send-document.blade.php
│       ├── 📁 folders
│       │   ├── 📁 modals
│       │   │   ├── 🐘 addFolder.blade.php
│       │   │   └── 🐘 addTag.blade.php
│       │   ├── 🐘 folder_item.blade.php
│       │   ├── 🐘 parentfolder.blade.php
│       │   ├── 🐘 subfolders.blade.php
│       │   ├── 🐘 table.blade.php
│       │   └── 🐘 tags.blade.php
│       ├── 📁 layouts
│       │   ├── 🐘 app.blade.php
│       │   ├── 🐘 header.blade.php
│       │   ├── 🐘 navbar-search.blade.php
│       │   ├── 🐘 navbar.blade.php
│       │   └── 🐘 shareApp.blade.php
│       ├── 📁 projects
│       ├── 📁 shares
│       │   ├── 🐘 documents.blade.php
│       │   ├── 🐘 expired.blade.php
│       │   ├── 🐘 index.blade.php
│       │   ├── 🐘 no-permission.blade.php
│       │   ├── 🐘 preview.blade.php
│       │   └── 🐘 singleDocument.blade.php
│       ├── 📁 tags
│       │   └── 🐘 table.blade.php
│       ├── 🐘 app.blade.php
│       └── 🐘 stegolock.blade.php
├── 📁 routes
│   ├── 🐘 api.php
│   ├── 🐘 auth.php
│   ├── 🐘 console.php
│   └── 🐘 web.php
├── 📁 storage
│   ├── 📁 app
│   │   ├── 📁 public
│   │   │   └── ⚙️ .gitignore
│   │   └── ⚙️ .gitignore
│   ├── 📁 framework
│   │   ├── 📁 sessions
│   │   │   └── ⚙️ .gitignore
│   │   ├── 📁 testing
│   │   │   ├── 📁 disks
│   │   │   │   └── 📁 local
│   │   │   │       └── 📁 documents
│   │   │   │           └── 📄 test.txt
│   │   │   └── ⚙️ .gitignore
│   │   ├── 📁 views
│   │   │   ├── ⚙️ .gitignore
│   │   │   ├── 🐘 2ca6ed0181e363a5d05d716eab325fff.php
│   │   │   ├── 🐘 40be07a0607cb7c172be841f8e017c9d.php
│   │   │   ├── 🐘 496aec9a167afb4b2005f9e624ce8b26.php
│   │   │   ├── 🐘 50b66dc2640bd95b5c61fee3b7a6cb3b.php
│   │   │   ├── 🐘 736482df08da65267bb29bab13ce264c.php
│   │   │   ├── 🐘 7cd076e644019c4e7bd081111a01e141.php
│   │   │   ├── 🐘 7e568f2c0d5b98a9af64f9c5d6368a01.php
│   │   │   ├── 🐘 b28b911d65acfdff235bba452b84c0f9.php
│   │   │   └── 🐘 f01eae0e7cdf98f78ec574ec45b71b10.php
│   │   └── ⚙️ .gitignore
│   └── 📁 logs
│       └── ⚙️ .gitignore
├── 📁 tests
│   ├── 📁 Feature
│   │   ├── 📁 Api
│   │   │   ├── 🐘 AuthTokenManagementTest.php
│   │   │   └── 🐘 StegoApiTest.php
│   │   ├── 📁 Auth
│   │   │   ├── 🐘 AuthenticationTest.php
│   │   │   ├── 🐘 EmailVerificationTest.php
│   │   │   ├── 🐘 MkdAuthTest.php
│   │   │   ├── 🐘 PasswordConfirmationTest.php
│   │   │   ├── 🐘 PasswordResetTest.php
│   │   │   ├── 🐘 PasswordUpdateTest.php
│   │   │   └── 🐘 RegistrationTest.php
│   │   ├── 🐘 ExampleTest.php
│   │   └── 🐘 ProfileTest.php
│   ├── 📁 Unit
│   │   ├── 🐘 CryptoServiceTest.php
│   │   ├── 🐘 ExampleTest.php
│   │   └── 🐘 SegmentationServiceTest.php
│   ├── 🐘 TestCase.php
│   ├── 🐘 phase3_api_test.php
│   └── 🐘 stegolock_phase_test.php
├── ⚙️ .editorconfig
├── ⚙️ .env.example
├── ⚙️ .gitattributes
├── ⚙️ .gitignore
├── 📝 CHANGES_2026-03-03.md
├── 📝 CHANGES_2026-03-04.md
├── 📝 CHANGES_2026-03-05.md
├── 📝 CHANGES_2026-03-06.md
├── 📝 README.md
├── 📄 artisan
├── 📄 check-routes.ps1
├── ⚙️ composer.json
├── 📄 erd.dbdiagram
├── 📄 erd.dbml
├── ⚙️ package.json
├── ⚙️ phpunit.xml
├── ⚙️ pnpm-lock.yaml
├── 📄 postcss.config.js
├── 🐘 server.php
├── 📄 tailwind.config.js
├── 🐘 test_stego_error.php
├── ⚙️ tsconfig.json
├── ⚙️ tsconfig.node.json
└── 📄 vite.config.js
```

---
*Generated by FileTree Pro Extension*