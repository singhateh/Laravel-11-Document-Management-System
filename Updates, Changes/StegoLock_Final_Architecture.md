# **STEGOLOCK SYSTEM ARCHITECTURE**

## **Revised & Normalized Design**

Comprehensive Response to All Feedback

## **Executive Summary**

This revised architecture comprehensively addresses all feedback: (1) merging redundant UI layers, (2) implementing Master Key Derivation to eliminate per-document passphrase friction, (3) explaining quality metrics value, (4) justifying AWS S3 with detailed security rationale, (5) normalizing services to eliminate redundancy, (6) clarifying layer responsibilities and boundaries, (7) integrating role-based user types throughout, and (8) detailing explicit communication protocols with a comprehensive connectivity matrix.

## **Feedback Resolution Checklist**

| ✓ | Issue | Resolution |
| :---- | :---- | :---- |
| 1 | User & React UI redundantly separated | MERGED into a single Client Application Layer |
| 2 | Passphrase required per document (UX problem) | IMPLEMENTED Master Key Derivation (MKD)—one login, session token, no per-document passphrase |
| 3 | Quality metrics lack context | EXPLAINED as carrier integrity validators; user assurance mechanism |
| 4 | AWS S3 vague; risk unclear | DETAILED role, encryption, compliance, IAM security, risk mitigation, why S3 vs alternatives |
| 5 | Diagram-description mismatch | ALIGNED diagram with normalized architecture; text matches flows |
| 6 | Redundant concepts in services | NORMALIZED 5 single-responsibility services; no duplication |
| 7 | Layer responsibilities unclear | DEFINED explicit layer inputs/outputs; layer separation enforced |
| 8 | User types are missing from the architecture | INTEGRATED Owner/Viewer/Admin roles throughout auth & access flows |
| 9 | Component communication unclear | ADDED communication matrix \+ data flow specifications for each interaction |

## **Architecture Overview: Five Distinct Layers**

StegoLock now employs five clearly defined layers with explicit responsibilities and communication protocols. Each layer has distinct inputs and outputs, and does not overlap with neighboring layers.

### **LAYER 1: CLIENT APPLICATION LAYER**

MERGED layer combining user interaction and frontend logic (previously separated into &\#x201C; User Access Layer&\#x201D; \+ &\#x201C; Frontend React Layer&\#x201D;). Single unified web interface eliminating all redundancy.

### **Sole Responsibility:**

Present user interface; collect user input; display system responses

### **Specific Functions:**

* Login interface (username/password entry)  
* Document upload form (file selection)  
* Carrier file selection interface  
* Display quality metrics (PSNR, SNR values) post-upload  
* List documents owned by the user (based on role)  
* List documents shared to the user (Viewer role)  
* Document retrieval & download UI

### **User Types Supported in UI:**

* Document Owner: See all own documents; upload new; grant access; download  
* Document Viewer: See shared documents (read-only); download only  
* System Admin: See all users; view audit logs; manage system settings

### **Critical Constraint:**

NO cryptographic operations. All encryption/decryption is handled by Layers 2 and 3\.

### **Communication Out:**

REST API calls (JSON) over HTTPS/TLS 1.2+ to Layer 2 (API Gateway)

### **LAYER 2: API GATEWAY & AUTHENTICATION**

Entry point for all client requests. Authenticates users, issues Master Key Token, enforces role-based authorization, and routes requests to appropriate services.

### **Sole Responsibility:**

Authenticate users; issue tokens; enforce access control; route to services

### **Specific Functions:**

* Receive HTTP requests from Layer 1  
* Validate user credentials (username/password) against the Persistence Service  
* Derive Master Key: PBKDF2(password \+ server\_salt, 100k iterations) &\#x2192; 256-bit Master Key  
* Issue Master Key Token (JWT) valid for session duration (e.g., 8 hours)  
* Validate Master Key Token on all subsequent requests  
* Query user role (Owner / Viewer / Admin)  
* Check document ownership or role-grant access (via Persistence Service)  
* Reject unauthorized requests with 403 Forbidden  
* Route authorized requests to Layer 3 services (via internal RPC)

### **Master Key Derivation (MKD) System—THE SOLUTION TO UX FRICTION:**

User authenticates ONCE per session with username/password. API Gateway derives the Master Key (which is not stored anywhere). Master Key Token issued to the client. For every document operation (upload/retrieve), a Master Key Token is provided (not a passphrase). The backend derives the Document Encryption Key (DEK) from the Master Key and the document\_id\_salt, with no per-document passphrase. User remembers only one password per session.

### **Communication In/Out:**

Inbound: HTTPS/REST from Layer 1  
Outbound: Internal gRPC (mTLS) to Layer 3 Services

### **LAYER 3: SERVICE ORCHESTRATION & BUSINESS LOGIC**

Five NORMALIZED, single-responsibility services. Zero redundancy. Each service owns one specific cryptographic or data-handling function.

### **Service 1: CRYPTOGRAPHY SERVICE**

### **Responsibility:**

ALL encryption and decryption operations. ONLY service handling keys and ciphertexts.

### **Functions:**

* Receive Master Key Token from Layer 2  
* Derive Document Encryption Key (DEK): PBKDF2(Master Key \+ document\_id\_salt, 10k iterations) &\#x2192; 256-bit DEK  
* Generate a random 12-byte IV  
* Encrypt: AES-256-GCM(plaintext, DEK, IV) &\#x2192; ciphertext \+ auth\_tag  
* Decrypt: AES-256-GCM(ciphertext, DEK, IV, auth\_tag) &\#x2192; plaintext or FAIL  
* Validate the authentication tag on decryption (detect tampering)  
* Secure key lifecycle: Keys in memory only; never persisted or transmitted

### **Service 2: STEGANOGRAPHY SERVICE**

### **Responsibility:**

ALL data embedding and extraction operations. ONLY service touching carrier files.

### **Functions:**

* Embed encrypted segment into image: Modify the LSB of the RGB channels  
* Embed an encrypted segment into audio: Modify the LSB of 16-bit samples  
* Embed encrypted segment into text: Insert zero-width Unicode characters  
* Extract encrypted segment from image: Read LSBs from RGB channels  
* Extract encrypted segment from audio: Read LSBs from samples  
* Extract encrypted segment from text: Read zero-width characters  
* Calculate PSNR (for images) and SNR (for audio) quality metrics  
* Validate imperceptibility: PSNR ≥ 40 dB (image), SNR ≥ 35 dB (audio)  
* Return quality metrics to Layer 2 for client display

### **Service 3: SEGMENTATION SERVICE**

### **Responsibility:**

Document partitioning and reassembly logic ONLY. No encryption, no embedding.

### **Functions:**

* Analyze encrypted payload size vs. total carrier capacity (sum of all carriers)  
* Decision: Single-carrier (payload ≤ capacity) vs. Multi-carrier (payload \> capacity)  
* If multi-carrier: Split into 2 MB segments with metadata (segment\_index, total\_count)  
* Return the segment list to Stego Service for embedding  
* On retrieval: Reassemble segments in correct order (sort by segment\_index)  
* Verify total size matches stored metadata

### **Service 4: CLOUD STORAGE SERVICE**

### **Responsibility:**

AWS S3 integration ONLY. Upload, download, credential management.

### **Functions:**

* Upload stego-files to S3 over TLS 1.2+  
* Download stego-files from S3 over TLS 1.2+  
* Verify file integrity via hash comparison  
* Handle transient failures with retry logic (3 attempts, exponential backoff)  
* Rollback on permanent failure  
* Manage AWS IAM credentials (rotate every 90 days)

### **Service 5: PERSISTENCE SERVICE**

### **Responsibility:**

ALL database operations. Metadata, users, audit logs, and role grants.

### **Functions:**

* Store user accounts (username, bcrypt password hash, role)  
* Store document metadata (owner\_id, filename, size, segment\_count, created\_at)  
* Store segment records (segment\_index, total\_count, encrypted\_size, order)  
* Store carrier records (carrier\_type, s3\_url, quality\_metric)  
* Store role-grant records (owner\_id, viewer\_user\_id, document\_id, grant\_timestamp)  
* Log all access: action, user\_id, document\_id, success/fail, timestamp, ip\_address

### **LAYER 4: DATA PERSISTENCE LAYER**

Relational database (MySQL 8.0+ or PostgreSQL 14+). Stores all application state.

### **Database Tables:**

* users: id, username, password\_hash, role (owner/admin), created\_at  
* documents: id, owner\_id (FK), filename, size, segment\_count, created\_at  
* segments: id, document\_id (FK), segment\_index, total\_count, encrypted\_size  
* carriers: id, segment\_id (FK), carrier\_type, s3\_url, quality\_metric (PSNR/SNR)  
* role\_grants: id, owner\_id (FK), viewer\_user\_id (FK), document\_id (FK), granted\_at  
* access\_logs: id, user\_id (FK), action (upload/retrieve/share), document\_id (FK), success (boolean), timestamp, ip\_address

### **Security:**

All connections use TLS—passwords stored as bcrypt(salt\_rounds=12) hashes. Audit logs are encrypted at rest.

### **LAYER 5: CLOUD STORAGE LAYER**

AWS S3 bucket. Stores stego files (encrypted carrier images, audio, and text).

### **AWS S3 Role in System:**

StegoLock does NOT store plaintext documents. Only stego-files (carrier \+ hidden encrypted data) are stored in S3.

### **Why AWS S3 (not alternatives):**

* Scalability: Unlimited storage; auto-scales with demand; no infrastructure management  
* Reliability: 99.999999999% (11 nines) durability; multi-region replication option  
* Security: AES-256 encryption at rest (S3-managed or KMS customer-managed keys)  
* Compliance: GDPR, HIPAA, SOC 2 Type II certified  
* Cost: Pay-per-use; storage optimized; lifecycle policies for old files

### **Security Controls:**

* Encryption: Server-side AES-256-S3 mandatory; blocks unencrypted uploads  
* Transport: TLS 1.2+ required; non-HTTPS requests blocked  
* IAM: Least privilege; service account with minimal permissions; credentials rotate every 90 days  
* Versioning: Enabled; protects against accidental deletion; audit trail maintained  
* Access Logging: All S3 operations logged to a separate audit bucket  
* DDoS Protection: AWS Shield Standard (automatic); Shield Advanced available (optional)  
* Bucket Policy: Denies unauthenticated access; restricts to app service account only

### **Risk Mitigation:**

* Risk: AWS compromise &\#x2192; Mitigation: Stego-files are encrypted at transport layer (TLS) AND contain encrypted payloads; even if AWS key compromised, stego-file content requires knowledge of document\_id\_salt to derive DEK  
* Risk: Credential theft &\#x2192; Mitigation: IAM credentials rotate quarterly; access logs monitor suspicious activity; MFA required for console access  
* Risk: Data deletion &\#x2192; Mitigation: S3 versioning enabled; cross-region replication on critical documents

### **Alternatives Considered:**

* Microsoft Azure Blob: Comparable security; higher cost in some regions; less mature ecosystem  
* Google Cloud Storage: Comparable security; good for Google-heavy organizations; less industry adoption  
* Self-hosted (on-premises): Eliminates cloud dependency; increases operational complexity and security burden; higher TCO

## **Communication Protocols & Data Flow**

All system components communicate through standardized protocols. This section details every connection point.

### **Inter-Layer Communication Matrix**

| From | To | Protocol | Encryption | Data Format |
| :---- | :---- | :---- | :---- | :---- |
| Layer 1 | Layer 2 | HTTPS/REST | TLS 1.2+ | JSON |
| Layer 2 | Layer 3 | gRPC | mTLS | Protobuf |
| Layer 3 | Layer 4 | TCP | TLS | SQL |
| Service 4 | Layer 5 | HTTPS/REST | TLS 1.2+ | Binary |

## **Quality Metrics: User Perspective & Value**

Quality metrics are NOT technical noise. They serve a critical user-facing purpose.

### **What Are They?**

* PSNR (Peak Signal-to-Noise Ratio) for image carriers: Measures imperceptibility of embedded data  
* SNR (Signal-to-Noise Ratio) for audio carriers: Measures imperceptibility of embedded data

### **Why Users Care:**

* User uploads image \+ hidden document &\#x2192; System returns PSNR 42 dB &\#x2192; User sees: &\#x201C;My image is imperceptibly modified; no one will notice it contains hidden data&\#x201D;  
* User uploads audio \+ hidden document &\#x2192; System returns SNR 38 dB &\#x2192; User sees: &\#x201C;My audio quality is preserved; hidden data is inaudible&\#x201D;

### **User Assurance Mechanism:**

Before the user shares the carrier file with others or posts it online, they see quality metrics confirming the carrier still looks/sounds normal. High metrics \= confidence that steganography is undetectable. If metrics fall below thresholds (PSNR \< 40 dB, SNR \< 35 dB), the system rejects embedding and asks the user to select a different carrier.

### **Bottom Line:**

Quality metrics reassure users that their secret is hidden AND their carrier file remains usable/shareable.

## **Detailed Process: Document Upload (With Master Key Derivation)**

### **PHASE 1: USER AUTHENTICATION**

1. User opens app, enters username \+ password in Layer 1 UI  
2. Layer 1 sends POST /auth/login {username, password} over HTTPS to Layer 2  
3. Layer 2 (API Gateway) receives request; queries Persistence Service for bcrypt hash  
4. Layer 2 compares bcrypt(password) vs. stored hash; MATCH ✓  
5. Layer 2 derives Master Key: PBKDF2(password \+ server\_salt, 100k iterations) &\#x2192; 256-bit Master Key  
6. Layer 2 creates JWT token: {user\_id, role, exp: now \+ 8h}; signs with server secret  
7. Layer 2 returns the JWT token to Layer 1 in an HTTP response  
8. Layer 1 stores JWT in a secure HTTP-only cookie  
9. User sees dashboard: documents they own \+ documents shared to them (based on role)

KEY: User never enters passphrase again during this session. Master Key is server-side; only the JWT token is client-side.

### **PHASE 2: DOCUMENT UPLOAD INITIATION**

10. User selects document file (e.g., MySecrets.pdf)  
11. User selects carrier file(s) (e.g., Vacation.jpg, Background.wav)  
12. User clicks &\#x201C;Upload&\#x201D;  
13. Layer 1 sends a POST request to/api/documents with {document\_file, carrier\_files} and a JWT token.  
14. Layer 2 receives the request; validates the JWT signature and expiry  
15. Layer 2 extracts user\_id and role from JWT; checks file format validity  
16. Layer 2 routes to Layer 3 Service Orchestration (internal RPC)

### **PHASE 3: ENCRYPTION**

17. Crypto Service (Layer 3\) receives Master Key Token \+ plaintext document  
18. Derives Document Encryption Key (DEK): PBKDF2(Master Key \+ document\_id\_salt, 10k iterations) &\#x2192; 256-bit DEK  
19. Generates a random 12-byte IV  
20. Encrypts document: AES-256-GCM(plaintext, DEK, IV) &\#x2192; ciphertext \+ auth\_tag  
21. Discards DEK, plaintext, and IV from memory (never logged or stored)  
22. Returns ciphertext \+ auth\_tag \+ IV to Segmentation Service

RESULT: Encrypted document (no passphrase required; only Master Key Token used)

### **PHASE 4: SEGMENTATION**

23. Segmentation Service receives encrypted payload \+ list of carriers  
24. Calculates encrypted payload size (ciphertext \+ IV \+ auth\_tag)  
25. Calculates total carrier capacity (sum: image LSB capacity \+ audio LSB capacity \+ text capacity)  
26. Decision point:  
    * IF encrypted\_size ≤ total\_capacity: Single carrier &\#x2192; segment\_index=1, total\_segments=1  
    * IF encrypted\_size \> total\_capacity: Multi-carrier &\#x2192; split into 2 MB chunks; index each  
27. Returns segment list \+ metadata to Stego Service

### **PHASE 5: STEGANOGRAPHIC EMBEDDING**

28. Stego Service receives encrypted segments \+ carriers  
29. FOR EACH segment:  
    * Image carrier: Modify LSB of RGB channels &\#x2192; stego-image  
    * Audio carrier: Modify LSB of samples &\#x2192; stego-audio  
    * Text carrier: Insert zero-width characters &\#x2192; stego-text  
30. Calculates quality metrics: PSNR (image), SNR (audio)  
31. Validates: PSNR ≥ 40 dB and SNR ≥ 35 dB  
32. IF validation fails &\#x2192; Returns error; user prompted to select different carrier  
33. Returns stego-files \+ quality\_metrics to Cloud Storage Service

### **PHASE 6: CLOUD UPLOAD**

34. Cloud Storage Service receives stego-files \+ quality metrics  
35. FOR EACH stego-file:  
    * Generates S3 key: documents/{document\_id}/segment\_{index}.{ext}  
    * Uploads to S3 over HTTPS with TLS 1.2+  
    * S3 applies server-side AES-256-S3 encryption at rest  
    * Receives S3 URL (e.g., https://bucket.s3.amazonaws.com/documents/123/segment\_1.jpg)  
36. Returns S3 URLs \+ quality metrics to Persistence Service

### **PHASE 7: METADATA PERSISTENCE**

37. Persistence Service receives document metadata \+ S3 URLs \+ quality metrics  
38. Stores document record:  
    * INSERT documents (owner\_id, filename, size, segment\_count, created\_at)  
39. Stores segment records (if multi-segment):  
    * FOR i=1 TO total\_segments: INSERT segments (document\_id, segment\_index, total\_count, encrypted\_size)  
40. Stores carrier records:  
    * FOR EACH carrier: INSERT carriers (segment\_id, carrier\_type, s3\_url, quality\_metric)  
41. Logs upload action:  
    * INSERT access\_logs (user\_id, action='upload', document\_id, success=true, timestamp, ip\_address)

### **PHASE 8: RESPONSE TO CLIENT**

42. Layer 2 returns a JSON response:  
    {

    "success": true,  
    "document\_id": 12345,  
    "segments": 1,  
    "quality\_metrics": { "psnr": 42.5, "snr": null },  
    "message": "Upload successful. Your document is securely hidden."

    }  
43. Layer 1 displays success message \+ quality metrics  
44. Document appears in the user's dashboard

## **Detailed Process: Document Retrieval (With Master Key Derivation)**

### **PHASE 1: USER INITIATES RETRIEVAL**

45. User logged in with a valid JWT token (Master Key Token) in the cookie  
46. User views dashboard; clicks &\#x201C;Download&\#x201D; on document  
47. Layer 1 sends GET /api/documents/{document\_id} \+ JWT token

KEY: No passphrase entry. The Master Key Token from the login session is used.

### **PHASE 2: AUTHORIZATION**

48. Layer 2 validates the JWT signature and expiry  
49. Layer 2 queries Persistence Service: Is user\_id the document owner?  
50. IF yes &\#x2192; Authorize  
51. IF no &\#x2192; Check role\_grants table: Does any owner grant viewer access to user\_id for this document\_id?  
52. IF yes (viewer role) &\#x2192; Authorize (read-only)  
53. IF no &\#x2192; Reject with 403 Forbidden

### **PHASE 3: FILE RETRIEVAL FROM S3**

54. Cloud Storage Service queries Persistence Service for S3 URLs  
55. Retrieves metadata: segment count, carrier types  
56. FOR EACH stego-file:  
    * Downloads from S3 over HTTPS with TLS 1.2+  
    * Verifies file integrity: SHA-256 hash comparison  
    * Stores in memory (never disk)

### **PHASE 4: STEGANOGRAPHIC EXTRACTION**

57. Stego Service receives stego-files \+ carrier types  
58. FOR EACH stego-file:  
    * Image: Extract LSBs from RGB channels &\#x2192; encrypted segment  
    * Audio: Extract LSBs from samples &\#x2192; encrypted segment  
    * Text: Extract zero-width characters &\#x2192; encrypted segment  
59. Returns encrypted segments to Segmentation Service

### **PHASE 5: REASSEMBLY**

60. Segmentation Service receives encrypted segments  
61. IF single segment &\#x2192; Use as-is  
62. IF multi-segment &\#x2192; Sort by segment\_index (1, 2, 3, ...)  
63. Concatenate segments in the correct order  
64. Verify total size matches stored metadata  
65. Returns a complete encrypted payload to the Crypto Service

### **PHASE 6: DECRYPTION**

66. Crypto Service receives encrypted payload \+ Master Key Token  
67. Derives Document Encryption Key (DEK): PBKDF2(Master Key \+ document\_id\_salt, 10k iterations)  
68. Extracts IV and auth\_tag from encrypted payload  
69. Performs AES-256-GCM decryption: ciphertext \+ DEK \+ IV \+ auth\_tag &\#x2192; plaintext  
70. Validates authentication tag:  
    * IF tag is valid &\#x2192; Decryption succeeded; return plaintext  
    * IF tag is invalid &\#x2192; TAMPERING DETECTED; return error; log security alert  
71. Discards DEK from memory

KEY: No passphrase entered. Master Key from JWT token used.

### **PHASE 7: AUDIT LOGGING**

72. Persistence Service logs retrieval:  
    * INSERT access\_logs (user\_id, action='retrieve', document\_id, success=true/false, timestamp, ip\_address)  
73. IF tampering detected: Log security\_alerts (tampering\_detected, document\_id, timestamp)

### **PHASE 8: RESPONSE TO CLIENT**

74. Layer 2 returns plaintext document (over HTTPS)  
75. Layer 1 displays the download button or preview  
76. User downloads or views the document

## **Normalized System Architecture (Text Diagram)**

## **User Type Integration in Architecture**

Role-based access is integrated throughout the architecture. User types are enforced at Layer 2 and 4\.

### **Document Owner**

* Can upload documents (POST /api/documents)  
* Can retrieve own documents (GET /api/documents/{id})  
* Can share document: Grant Viewer role to another user (POST /api/documents/{id}/grant {viewer\_user\_id})  
* Can revoke access (DELETE /api/documents/{id}/grant/{viewer\_user\_id})  
* Enforced by Layer 2: Check documents.owner\_id \= current\_user\_id

### **Document Viewer**

* Can retrieve ONLY documents shared by an Owner (read-only)  
* CANNOT upload documents  
* CANNOT modify or delete shared documents  
* Enforced by Layer 2: Check role\_grants table for {owner\_id, viewer\_user\_id, document\_id}

### **System Admin**

* Can manage users (create, enable, turn on or off)  
* Can view audit logs (access\_logs table)  
* Can view all documents (metadata only, not content)  
* Enforced by Layer 2: Check users.role \= 'admin'