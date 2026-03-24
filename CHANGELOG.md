# Changelog

All notable changes to the StegoLock project will be documented in this file.

## [Unreleased]

### Added
- **Auto-Selection of Carriers from User's Pool** (2026-03-24)
  - Automatic scanning of user's carrier pool to select necessary carriers
  - One-click auto-selection based on document requirements
  - Greedy bin-packing algorithm (largest carriers first) to minimize number needed
  - Real-time display of selected carriers with capacity information
  - Clear indication of total capacity and sufficiency check
  - Option to clear auto-selection and manually choose carriers
  - Integration with preflight verification system
  - Updated encoding summary to show auto-selected carriers
  - Updated aggregate capacity tracker to include auto-selected capacity
  - Success message now includes auto-selected carrier count

- **System Carrier Backup Transparency UI** (2026-03-24)
  - Clear indication when system carriers are being used during encoding
  - Separate display of pool capacity vs. system backup capacity
  - Visual distinction between user carriers and system backup carriers
  - Real-time status of system carrier usage
  - Detailed capacity breakdown showing user pool vs. system backup
  - Visual capacity bar with distribution indicators
  - Informational section explaining how system backup works

### Changed
- **Encode Page - One-Time Setup Carrier Pool Concept** (2026-03-24)
  - Changed step label from "Upload Carriers" to "Choose Carriers" to reflect the new flow
  - Added "Available Carriers from Your Pool" section showing pre-validated carriers ready to use
  - System carriers are now enabled by default when available
  - Added warning message when no carriers are available in the pool
  - Updated description to emphasize using carriers from the pool rather than uploading new ones
  - Aligned UI with the guide's "one-time setup" concept where carriers are uploaded once to a reusable pool

## [1.0.0] - 2026-03-20

### Added
- Initial release of StegoLock
- Document encoding with steganography
- Carrier pool management
- System carrier backup functionality
- Capacity planning tools
- Smart carrier selection UI
- Preflight verification system
