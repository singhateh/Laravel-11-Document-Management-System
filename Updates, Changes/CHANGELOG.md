# StegoLock Changelog

All notable changes to the StegoLock project will be documented in this file.

## [Unreleased]

### Added
- **System Carrier Backup Transparency UI** (2026-03-24)
  - Clear indication when system carriers are being used during encoding
  - Separate display of pool capacity vs. system backup capacity
  - Visual distinction between user carriers and system backup carriers
  - Real-time status of system carrier usage
  - Detailed capacity breakdown showing user pool vs. system backup
  - Visual capacity bar with distribution indicators
  - Informational section explaining how system backup works

- **Smart Carrier Selection UI** (2026-03-24)
  - Visibility into which carriers the system automatically selected
  - Explanation of selection criteria (capacity, availability, quality)
  - Real-time carrier selection visualization
  - Selection rank display for each carrier

- **Capacity Planning Tools** (2026-03-24)
  - Pre-encoding capacity check showing document requirements vs. available pool capacity
  - Recommendations for how many additional carriers are needed
  - Preflight verification before starting encoding
  - Detailed capacity breakdown with progress visualization

### Changed
- Enhanced Encode page with comprehensive capacity planning and carrier selection tools
- Improved user experience with real-time feedback during encoding process

### Fixed
- N/A

## [1.0.0] - 2026-03-20

### Added
- Initial release of StegoLock
- Document encoding with steganography
- Carrier image management
- System carrier pool support
- User authentication and authorization
- Document sharing and access control
