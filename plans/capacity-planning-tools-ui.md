# Capacity Planning Tools UI Enhancement Plan

## Overview
This plan outlines the UI enhancements needed for the Encode page to implement the Capacity Planning Tools feature, which includes:
- Pre-encoding capacity check showing document requirements vs. available pool capacity
- Recommendations for how many additional carriers are needed
- Preflight verification before starting encoding

## Current State Analysis
From reviewing `resources/js/Pages/Stego/Encode.tsx`, the following capacity-related functionality already exists:
- `dataNeeded`: Calculates bytes needed for selected document (using gzip 0.4 × base64 4/3 pipeline)
- `totalCapacity`: Sum of capacities from user-selected carriers
- `systemCapacity`: Sum of capacities from system carriers (when `useSystemCarriers` is enabled)
- `effectiveCapacity`: Total available capacity (user + system carriers)
- `capacityOk`: Boolean check if effectiveCapacity >= dataNeeded
- UI elements showing "Total carrier capacity" vs "MB needed"
- Encoding summary section showing estimated carriers needed

## Planned Enhancements

### 1. Enhanced Capacity Check Display
**Location:** Replace/extend the current capacity tracker (lines 493-500)

**Changes:**
- Show detailed breakdown: Document requirements, User carrier capacity, System carrier capacity, Total available
- Visual progress bar indicating capacity utilization percentage
- Color-coded status (green when sufficient, red when insufficient)
- Tooltip/show more details on hover

**UI Components:**
```
<div className="capacity-check">
  <div className="capacity-header">
    <h4>Capacity Check</h4>
    <div className="status-indicator">
      {/* Green check if sufficient, red warning if not */}
    </div>
  </div>
  
  <div className="capacity-details">
    <div className="capacity-item">
      <span>Document Requirements:</span>
      <span className="value">{dataNeededFormatted}</span>
    </div>
    <div className="capacity-item">
      <span>Your Carriers:</span>
      <span className="value">{userCapacityFormatted}</span>
    </div>
    <div className="capacity-item">
      <span>System Pool:</span>
      <span className="value">{systemCapacityFormatted}</span>
    </div>
    <div className="capacity-item total">
      <span>Total Available:</span>
      <span className="value">{effectiveCapacityFormatted}</span>
    </div>
  </div>
  
  <div className="capacity-progress">
    <div className="progress-bar" style={{ width: utilizationPercentage + '%' }}>
      {/* Filled portion */}
    </div>
    <div className="progress-text">
      {utilizationPercentage}% Utilized
    </div>
  </div>
  
  {/* Recommendation section */}
  {recommendedAdditionalCarriers > 0 && (
    <div className="recommendation">
      <span>Recommendation:</span>
      <span>Add {recommendedAdditionalCarriers} more carrier(s) for optimal performance</span>
    </div>
  )}
</div>
```

### 2. Preflight Verification System
**Location:** Integrate into form submission flow (around `handleSubmit` function)

**Changes:**
- Run validation before allowing form submission
- Prevent submission if capacity is insufficient
- Show blocking warning with specific recommendations
- Option to proceed anyway with warning (for advanced users)

**Implementation:**
```typescript
const handleSubmit = (e: FormEvent) => {
  e.preventDefault();
  
  // Preflight verification
  const verification = runPreflightVerification();
  
  if (!verification.passed && !verification.userOverride) {
    // Show verification errors and recommendations
    setVerificationErrors(verification.errors);
    setVerificationRecommendations(verification.recommendations);
    return; // Block submission
  }
  
  // Proceed with submission if passed or user overridden
  // ... existing submission logic
};

const runPreflightVerification = () => {
  const errors = [];
  const recommendations = [];
  
  // Check 1: Document selected
  if (!data.document_id) {
    errors.push('Please select a document to encode');
  }
  
  // Check 2: Carriers provided (if not using system carriers)
  if (!useSystemCarriers && carriers.length === 0) {
    errors.push('Please upload carrier images or enable system carrier pool');
  }
  
  // Check 3: Capacity sufficient
  if (effectiveCapacity < dataNeeded) {
    const shortage = dataNeeded - effectiveCapacity;
    const additionalNeeded = Math.ceil(shortage / averageCarrierCapacity);
    
    errors.push('Insufficient carrier capacity for selected document');
    recommendations.push(
      `Add approximately ${additionalNeeded} more carrier image(s) to meet requirements`
    );
    
    // Provide specific recommendations based on carrier pool stats
    if (systemCarriers.length > 0) {
      const validSystemCarriers = systemCarriers.filter(c => c.validation_status === 'valid');
      if (validSystemCarriers.length > 0) {
        recommendations.push(
          `Consider enabling system carrier pool to access ${validSystemCarriers.length} pre-validated carriers`
        );
      }
    }
  }
  
  // Check 4: Carrier validation status
  const invalidCarriers = carriers.filter(c => c.validation_status === 'invalid');
  if (invalidCarriers.length > 0) {
    errors.push(`${invalidCarriers.length} carrier(s) failed validation and cannot be used`);
  }
  
  const passing = errors.length === 0;
  
  return {
    passed: passing,
    errors,
    recommendations,
    userOverride: false // Would be set to true if user chooses to proceed despite warnings
  };
};
```

### 3. Integration with Carrier Pool
**Location:** Enhanced display and recommendations

**Changes:**
- Show information about available system carriers when viewing capacity check
- Provide one-click option to enable/system carriers
- Show validation status of carriers in recommendations

### 4. Visual Design Guidelines
- Use existing Tailwind CSS classes from the project
- Follow the same design patterns as existing notifications and cards
- Maintain consistency with the Encode page's existing styling
- Use appropriate icons (📊 for capacity, 💡 for recommendations, 🔍 for verification)

### 5. Implementation Steps
1. Create new utility functions for capacity calculations and formatting
2. Enhance the capacity check display component
3. Implement preflight verification logic
4. Integrate verification into form submission flow
5. Add recommendation engine based on carrier pool analysis
6. Update UI with new components and styling
7. Test edge cases (no carriers, insufficient capacity, etc.)

### 6. Files to Modify
- `resources/js/Pages/Stego/Encode.tsx` - Main implementation

### 7. Dependencies
- Existing carrier capacity calculation functions
- Existing system carriers prop
- Existing useForm hook from InertiaJS
- No new external dependencies required

## Acceptance Criteria
1. [ ] Users can see detailed capacity breakdown before encoding
2. [ ] System provides clear recommendations when additional carriers are needed
3. [ ] Encoding is blocked when capacity is insufficient (unless user overrides)
4. [ ] Preflight verification runs and shows specific actionable feedback
5. [ ] UI maintains consistency with existing Encode page design
6. [ ] All existing functionality continues to work as expected

## Notes
- The enhancement should build upon existing capacity calculation logic
- Should handle edge cases like zero-capacity carriers gracefully
- Recommendations should be based on actual carrier capacity statistics
- Preflight verification should be fast and not block UI thread