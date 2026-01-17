# Changelog

All notable changes to the TMMS-24 Moodle Block will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.2] — 2026-01-16

### Added
- Option to show/hide descriptions in the main block.

### Changed
- The title "Socio-Emotional Skills Exploration" is maintained in all block views.
- References to the word "Test" and the name "TMMS 24" have been removed.

## [3.0.1] — 2026-01-08

### Added
- Pagination in the student list in the administration panel.

### Changed
- Use of Mustache template architecture for all block views.
- Complete code refactoring to separate presentation logic.
- Improved code maintainability and scalability.
- Optimized search performance.
- Minor security improvements.

## [3.0.0] — 2025-12-22

### Added
- **Multilingual Support:** Implementation of internationalization for Spanish and English.
- **Automatic Saving:** System for persisting answers and real-time progress.

### Changed
- **UI/UX Redesign:** Complete and modern redesign of the block interface, tests, teacher view, and individual views.
- **Responsive Design:** Optimization of all interfaces for mobile devices and tablets.
- **User Flow:** Improved navigation experience for teacher and student profiles.
- **Visual Identity:** Integration of institutional logos and application of the official color palette.
- **Standardization:** Visual and functional consistency with the `chaside`, `learning_style` and `personality_test` blocks.
- **Performance:** Optimization of resource loading and script execution.

### Fixed
- Minor bugs found in previous versions have been fixed.

### Security
- Security improvements have been implemented for data handling and access.

## [2.2.0] - 2025-09-30

### Added
- **Visual Design Overhaul**: Implemented consistent visual styling matching chaside block design
- **Enhanced Student Results**: Students now see the same detailed results format as teachers
- **Direct Navigation**: Clicking "View Results" now goes directly to detailed results without intermediate page
- **New Psychological Interpretation Rules**: Updated with official TMMS-24 gender-specific scoring guidelines
- **Card-based Interface**: Results displayed in modern card layout with color-coded dimensions
- **Improved Block Display**: Enhanced visual presentation with gradients, shadows, and hover effects

### Changed
- **Interpretation System**: Completely updated psychological assessment rules with research-backed ranges
- **Result Display Format**: Changed from table-based to card-based layout for better readability
- **Student Experience**: Streamlined navigation removing unnecessary intermediate steps
- **Visual Consistency**: Applied unified design language across all views (student, teacher, management)
- **Button Styling**: Enhanced with hover effects and modern design elements

### Fixed
- **Array Structure Issues**: Resolved PHP warnings related to interpretation array access
- **Translation Gaps**: Added missing translation strings for new interface elements
- **String Identifier Errors**: Fixed issues with numeric string identifiers in language files
- **URL Routing**: Corrected administrator button routing to proper dashboard view

### Technical Improvements
- **CSS Integration**: Added comprehensive styling matching chaside design patterns
- **Code Structure**: Improved organization of interpretation logic and result display
- **Translation System**: Expanded bilingual support for new interface elements
- **Error Handling**: Better handling of edge cases in result display

## [2.2.3] - 2025-10-13

### Fixed
- CSV export: fixed missing score and interpretation columns in exported CSV when facade returns Spanish keys. Now supports both Spanish and English keys and handles missing fields safely.

### Changed
- Minor robustness improvements to export script and protections against empty datasets.

## [1.0.0] - 2025-09-23

### Added
- Initial release of TMMS-24 Moodle Block
- Complete implementation of the 24-item Trait Meta-Mood Scale psychological test
- Role-based access control:
  - Students can take the test and view their results
  - Teachers/Administrators can view all results and statistics
- Multilingual support (English and Spanish)
- Three-dimensional scoring system:
  - Perception of emotions (8 items)
  - Comprehension of emotions (8 items) 
  - Regulation of emotions (8 items)
- Gender-specific interpretation guidelines
- Statistics dashboard for teachers showing:
  - Total completed tests
  - Average scores per dimension
  - Individual student results table
- CSV export functionality for all results
- Database schema for efficient data storage
- Responsive design integrated with Moodle theme
- Comprehensive capability system for access control
- Student results display directly in block
- Detailed results page with full interpretations

### Security
- Capability-based access control preventing unauthorized access
- Input validation for all form submissions
- Secure export functionality restricted to authorized users

### Requirements
- Moodle 4.1 or higher
- PHP 7.4 or higher

### Database Schema
- Single table `tmms_24` storing:
  - User and course identification
  - Individual item responses (1-5 scale)
  - Demographics (age, gender)
  - Timestamps for completion tracking
