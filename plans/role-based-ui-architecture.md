# Role-Based UI Architecture Diagram

```mermaid
graph TD
    A[User Login] --> B{User Role}
    
    B -->|Admin| C[Admin Dashboard]
    B -->|Owner| D[Owner Dashboard]
    B -->|User| E[User Dashboard]
    
    C --> F[User Management]
    C --> G[System Settings]
    C --> H[Document Management]
    C --> I[Folder Management]
    C --> J[StegoLock Encoding]
    C --> K[StegoLock Decoding]
    C --> L[API Token Management]
    
    D --> H
    D --> I
    D --> J
    D --> K
    D --> L
    
    E --> H
    E --> I
    E --> J
    E --> K
    E --> L
    
    F --> M[List Users]
    F --> N[Change Roles]
    F --> O[View Permissions]
    
    M --> P[User List Table]
    N --> Q[Role Dropdown]
    O --> R[Permission Matrix]
    
    H --> S[Upload Documents]
    H --> T[View Documents]
    H --> U[Download Documents]
    H --> V[Delete Documents]
    
    I --> W[Create Folders]
    I --> X[View Folders]
    I --> Y[Delete Folders]
    
    J --> Z[Encode Document]
    K --> AA[Decode Document]
    L --> BB[Manage API Tokens]
    
    classDef admin fill:#FF6B6B,stroke:#E63946,stroke-width:2px,color:#fff
    classDef owner fill:#4CC9F0,stroke:#457B9D,stroke-width:2px,color:#fff
    classDef user fill:#4D908E,stroke:#277DA1,stroke-width:2px,color:#fff
    classDef allRoles fill:#90BE6D,stroke:#2A9D8F,stroke-width:2px,color:#fff
    
    class C,F,M,N,O admin
    class D,F,M,N,O owner
    class E,H,I,J,K,L,S,T,U,V,W,X,Y,Z,AA,BB user
    class H,I,J,K,L,S,T,U,V,W,X,Y,Z,AA,BB allRoles
```

## Role-Based Navigation Structure

### Admin Navigation
- **Dashboard** - Overview with aggregate statistics
- **Users** - Manage user roles and permissions
- **Documents** - Full document management
- **Folders** - Full folder management
- **Tags** - Full tag management
- **Categories** - Full category management
- **🔒 StegoLock** - Encode, Decode, My Docs, API Tokens

### Owner Navigation
- **Dashboard** - Overview with aggregate statistics
- **Documents** - Full document management
- **Folders** - Full folder management
- **Tags** - Full tag management
- **Categories** - Full category management
- **🔒 StegoLock** - Encode, Decode, My Docs, API Tokens

### User Navigation
- **Dashboard** - Overview with personal statistics
- **Documents** - Personal document management
- **Folders** - Personal folder management
- **🔒 StegoLock** - Encode, Decode, My Docs, API Tokens

## Role-Based Feature Access

### Admin Features
- ✅ View all users and their roles
- ✅ Change user roles
- ✅ View role permissions
- ✅ Full system access
- ✅ Manage API tokens
- ✅ Encode/decode stego documents
- ✅ Manage all documents/folders/tags/categories

### Owner Features
- ✅ View all users and their roles
- ✅ Change user roles
- ✅ View role permissions
- ✅ Manage team documents/folders/tags/categories
- ✅ Manage API tokens
- ✅ Encode/decode stego documents

### User Features
- ✅ View own role information
- ✅ Manage personal documents/folders
- ✅ Manage own API tokens
- ✅ Encode/decode own stego documents
- ✅ Share documents with others

## Permission Matrix

| Resource | Action | Admin | Owner | User |
|----------|--------|-------|-------|------|
| user | create | ✅ | ❌ | ❌ |
| user | read | ✅ | ✅ | ✅ (own) |
| user | update | ✅ | ✅ | ✅ (own) |
| user | delete | ✅ | ❌ | ❌ |
| document | create | ✅ | ✅ | ✅ |
| document | read | ✅ | ✅ | ✅ (own) |
| document | update | ✅ | ✅ | ✅ (own) |
| document | delete | ✅ | ✅ | ✅ (own) |
| folder | create | ✅ | ✅ | ✅ |
| folder | read | ✅ | ✅ | ✅ (own) |
| folder | update | ✅ | ✅ | ✅ (own) |
| folder | delete | ✅ | ✅ | ✅ (own) |
| tag | create | ✅ | ✅ | ❌ |
| tag | read | ✅ | ✅ | ✅ |
| tag | update | ✅ | ✅ | ❌ |
| tag | delete | ✅ | ✅ | ❌ |
| category | create | ✅ | ✅ | ❌ |
| category | read | ✅ | ✅ | ✅ |
| category | update | ✅ | ✅ | ❌ |
| category | delete | ✅ | ✅ | ❌ |
| stego_document | create | ✅ | ✅ | ✅ |
| stego_document | read | ✅ | ✅ | ✅ (own) |
| stego_document | update | ✅ | ✅ | ✅ (own) |
| stego_document | delete | ✅ | ✅ | ✅ (own) |
| system | manage | ✅ | ❌ | ❌ |
| system | view_logs | ✅ | ❌ | ❌ |
| system | configure_settings | ✅ | ❌ | ❌ |
| team | manage | ❌ | ✅ | ❌ |
| team | invite_user | ❌ | ✅ | ❌ |
| team | remove_user | ❌ | ✅ | ❌ |
| profile | update | ✅ | ✅ | ✅ |
| profile | change_password | ✅ | ✅ | ✅ |
