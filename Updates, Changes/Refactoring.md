Based on my read-only architecture audit of your `app/` and `resources/` directories, here are the **top 5 largest files** that are the best candidates for being broken down:

---

## 1. **[`resources/js/Pages/Stego/Encode.tsx`](resources/js/Pages/Stego/Encode.tsx)** — 71,624 chars
**Why:** This is by far the largest file in the entire codebase. At ~72KB, this is almost certainly a massive UI component that has grown to handle too many responsibilities—likely combining form state management, validation, file upload logic, encoding workflow, and UI rendering all in one place. This is a prime candidate for extracting custom hooks, sub-components, and potentially splitting into multiple page-level components.

---

## 2. **[`app/Services/Stego/StegoDocumentService.php`](app/Services/Stego/StegoDocumentService.php)** — 26,184 chars
**Why:** This service is over 26KB, suggesting it's a "God service" that handles too many concerns—likely document creation, encoding orchestration, permission management, carrier selection, and persistence. It should be broken into smaller, single-responsibility services (e.g., `StegoDocumentCreationService`, `StegoEncodingOrchestrator`, `StegoPermissionService`).

---

## 3. **[`app/Http/Controllers/Api/StegoDocumentController.php`](app/Http/Controllers/Api/StegoDocumentController.php)** — 21,317 chars
**Why:** At ~21KB, this API controller is handling far too many endpoints and business logic. Controllers should be thin—delegating to services. This one likely contains validation, business logic, and response formatting that should be extracted into dedicated services or action classes.

---

## 4. **[`app/Services/DocumentService.php`](app/Services/DocumentService.php)** — 21,121 chars
**Why:** Another massive service at ~21KB, this likely handles document CRUD, file storage, permissions, sharing, and notifications all in one place. It's a strong candidate for decomposition into `DocumentStorageService`, `DocumentSharingService`, `DocumentPermissionService`, etc.

---

## 5. **[`resources/js/Pages/Welcome.tsx`](resources/js/Pages/Welcome.tsx)** — 32,016 chars
**Why:** At ~32KB, this welcome/landing page component is unusually large. It likely contains extensive inline styles, complex animations, multiple sections, and possibly embedded logic that should be extracted into separate components like `HeroSection`, `FeaturesSection`, `PricingSection`, etc.

---

### Summary Pattern

The codebase shows a clear pattern: **the Stego feature domain has grown organically** and now has several massive files across both the backend (services/controllers) and frontend (React components). The `Encode.tsx` component and `StegoDocumentService.php` are particularly egregious and should be prioritized for refactoring.