# Chapter 3 Update - Changes Summary

## MAJOR CHANGES BASED ON FEEDBACK:

### 1. REPLACED INPUT-PROCESS-OUTPUT (IPO) FRAMEWORK
**OLD**: Traditional IPO model (Input → Process → Output stages)
**NEW**: 5-Layer System Architecture (more appropriate for system-level view):
- Layer 1: Client Application Layer
- Layer 2: API Gateway & Authentication
- Layer 3: Service Orchestration & Business Logic
- Layer 4: Data Persistence Layer  
- Layer 5: Cloud Storage Layer

### 2. ADDED USER REGISTRATION WORKFLOW
**Issue**: Original framework started with authentication, but registration must come first
**Solution**: Added complete Section 3.2.1 "User Registration Workflow" showing:
- Registration interface and form submission
- Input validation (email, username, password strength)
- Bcrypt password hashing (12 salt rounds)
- Database account creation
- Response and redirect to login

### 3. CLARIFIED SEGMENTATION ("IF NECESSARY")
**Issue**: "if necessary" was vague - what determines necessity?
**Solution**: Added explicit explanation:
- Segmentation Decision Logic clearly defined
- Capacity Analysis formula provided: (width × height × 3) ÷ 8 bytes
- Decision criteria spelled out:
  * IF payload ≤ carrier capacity → Single-carrier embedding (no segmentation)
  * IF payload > carrier capacity → Multi-carrier segmentation REQUIRED
- Dynamic capacity-based segmentation explained (not fixed 2MB chunks)

### 4. UPDATED FRAMEWORK TO REFLECT BACKEND IMPLEMENTATION
**Changes marked throughout**:
- ■ CHANGED: Authentication (JWT → Laravel Sanctum with server-side sessions)
- ■ CHANGED: Cloud Storage (AWS S3 → Cloudflare R2 for production, Local for testing)
- ■ CHANGED: Master Key Storage (client JWT → server-side session for security)
- ■ ENHANCED: Asynchronous job processing (EncodeStegoDocumentJob, DecodeStegoDocumentJob)
- ■ ENHANCED: Dual steganography drivers (Python default + PHP GD fallback)
- ■ ENHANCED: Dynamic capacity-based segmentation
- ■ ENHANCED: Database schema (status tracking, compression flags, 3NF)

### 5. REORGANIZED CHAPTER STRUCTURE
**OLD Structure**:
- 3.1 Conceptual Framework (IPO stages)
- 3.2 Development Methodology
- 3.3 Ethical Considerations

**NEW Structure**:
- 3.1 System Architecture (5 layers - system-level view)
- 3.2 Operational Workflows
  - 3.2.1 User Registration Workflow (NEW!)
  - 3.2.2 User Authentication Workflow  
  - 3.2.3 Document Upload Workflow (Encoding)
  - 3.2.4 Document Retrieval Workflow (Decoding)
- 3.3 Development Methodology
- 3.4 Ethical Considerations

### 6. ENHANCED SECURITY DETAILS
- Master Key Derivation (MKD) system fully explained
- PBKDF2 iteration counts specified (100,000 for Master Key, 10,000 for DEK)
- Server-side session storage (stego_mkd key) documented
- Secure key lifecycle (memory-only, sodium_memzero() wiping) explained
- Authentication tag validation for tamper detection detailed

### 7. ADDED INTERMEDIATE STEPS IN WORKFLOWS
Each workflow now shows ALL steps including:
- Token validation and authorization checks
- Master Key retrieval from session
- DEK derivation process
- Encryption/decryption with AES-256-GCM
- Compression/decompression with gzip
- Segmentation analysis and decision
- Quality metric calculation (PSNR)
- Storage upload/download with retry logic
- Hash verification for integrity
- Audit logging for accountability

## FIGURES NEEDED:

1. Figure 3.1: StegoLock System Architecture - Five-Layer Design
   (Shows layers 1-5 with communication protocols between them)

2. Figure 3.2: StegoLock Process Flow - Document Upload and Retrieval
   (Shows the complete workflow with all phases and intermediate steps)

3. Figure 3.3: Agile Development Methodology
   (Existing circular diagram can be reused)

