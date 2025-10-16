=== Pic Pilot Meta ===
Contributors: stephenhernandez
Tags: AI, image SEO, alt text, accessibility, broken images, media library, OpenAI, Gemini, dashboard, scanner
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete AI-powered image management suite. Generate metadata, scan accessibility issues, detect broken images, and automate upload processing with OpenAI and Gemini.

== Description ==

**Pic Pilot Meta** is a comprehensive AI-powered image management suite that transforms how you handle images, accessibility, and SEO on WordPress sites.

Complete image management solution with intelligent automation:

🤖 **AI-Powered Generation**
- Generate SEO-optimized titles and alt text using OpenAI GPT-4V or Google Gemini Pro Vision
- Context-aware descriptions with keyword integration
- Automatic processing on upload with customizable settings
- Professional-quality metadata in seconds

📊 **Accessibility Dashboard**
- Comprehensive image accessibility scanning across your entire site
- Priority-based issue identification (Critical/High/Medium)
- Real-time progress tracking and detailed reporting
- CSV export for professional accessibility audits
- Works with any theme or page builder

🔍 **Broken Images Scanner**
- Dedicated scanner for detecting broken image links and missing files
- Identifies external images, orphaned attachments, and broken references
- Batch processing with real-time progress indicators
- Professional reporting and management tools

📸 **Smart Media Management**
- Enhanced Media Library with filtering by missing alt text/titles
- One-click AI generation directly in Media Library
- Duplicate images with AI-generated metadata
- Universal page builder support (Elementor, Beaver Builder, Divi, Visual Composer)
- Bulk operations for processing multiple images

⚡ **Performance Optimized**
- Modern JavaScript with intelligent caching
- Request deduplication prevents API waste
- Throttled operations for smooth UI experience
- Memory leak prevention and cleanup

**Perfect for:** Content creators, SEO specialists, accessibility compliance teams, e-commerce sites, digital agencies, and any WordPress site requiring professional image management.

== Features ==

**🤖 AI-Powered Metadata Generation**
* OpenAI GPT-4o and Google Gemini integration
* Intelligent title and alt text generation
* Context-aware descriptions with keyword support
* Multiple response handling with best result selection
* Fallback mechanisms for robust operation

**📱 User Interface**
* Native WordPress Media Library integration
* Modal popup for AI tools with real-time preview
* Bulk operations for processing multiple images
* One-click generation buttons in attachment details
* Progress indicators and status messages
* Copy-to-clipboard functionality for logs

**⚡ Performance & Reliability**
* Request deduplication prevents API waste
* Intelligent throttling and caching mechanisms
* Memory leak prevention with automatic cleanup
* Modern MutationObserver for efficient DOM monitoring
* Comprehensive error handling and logging

**🔧 Technical Features**
* PSR-4 autoloading with Composer architecture
* Translation-ready (text domain: `pic-pilot-meta`)
* Extensible settings system with validation
* Debug mode for development and troubleshooting
* WordPress coding standards compliance

**🎯 Integration Points**
* Media Library grid and list views
* Image edit screens and modals
* Attachment details sidebar
* Bulk operations interface
* Settings page with comprehensive options

== Installation ==

1. **Upload and Activate**
   - Upload the plugin to your `/wp-content/plugins/` directory
   - Activate via Plugins → Pic Pilot: Studio

2. **Configure AI Settings**
   - Go to Settings → Pic Pilot Meta
   - Choose your AI provider (OpenAI or Google Gemini)
   - Add your API key
   - Configure generation settings and prompts

3. **Start Using AI Tools**
   - Open Media Library
   - Click on any image to open details
   - Use "🤖 AI Tools" button for metadata generation
   - Or use bulk operations for multiple images

**Requirements:**
- WordPress 5.6 or higher
- PHP 7.4 or higher
- OpenAI API key or Google Gemini API key
- cURL extension enabled

== Screenshots ==

1. Accessibility Dashboard with comprehensive scanning results and priority-based filtering
2. Media Library integration showing PicPilot column with generation buttons and filtering options
3. AI generation modal with image preview, keyword input, and multiple generation options
4. Broken Images Scanner interface with real-time detection and professional reporting
5. Settings panel with OpenAI and Gemini configuration plus upload auto-generation options
6. Enhanced fix modal showing image details, URL, and quick generation tools

== Roadmap ==

**🗓️ Version 2.3 - Multilanguage Support (Q1 2025)**
* Full internationalization (i18n) implementation
* Translation support for Spanish, French, German, Portuguese, Italian
* Language-specific AI prompt optimization
* RTL language support (Arabic, Hebrew)
* Community translation program launch

**🗓️ Version 2.4 - Enhanced AI Features (Q2 2025)**
* Claude AI integration (Anthropic)
* Advanced keyword extraction from post content
* Image classification and auto-tagging
* Custom prompt templates and variables
* AI model selection per image type

**🗓️ Version 2.5 - Workflow Automation (Q3 2025)**
* Automatic generation on image upload
* Scheduled batch processing
* Custom workflow rules and triggers
* Integration with popular page builders (Elementor, Divi, Beaver Builder)
* Advanced bulk operations with filtering

**🗓️ Version 3.0 - Enterprise Features (Q4 2025)**
* Multi-site network support
* Team collaboration and approval workflows
* Advanced analytics and usage reporting
* Custom AI model training
* White-label options for agencies
* API endpoints for third-party integrations

**🔮 Future Considerations**
* Image background removal integration
* Advanced image optimization features
* Social media metadata generation
* SEO score analysis and recommendations
* Integration with popular SEO plugins (Yoast, RankMath)

== Changelog ==

= 2.2.0 - Performance Optimization Release =
* **Performance**: Replaced deprecated DOMNodeInserted with modern MutationObserver
* **Performance**: Implemented intelligent throttling and request deduplication
* **Performance**: Added DOM query caching and memory leak prevention
* **Performance**: Optimized event handling and reduced console logging overhead
* **UI**: Added copy-to-clipboard functionality for debug logs
* **Fix**: Resolved duplicate request error messages in frontend
* **Dev**: Enhanced debugging tools with conditional logging

= 2.1.2 - Stability & Deduplication =
* **Fix**: Resolved multiple concurrent AJAX requests causing flickering UI
* **Fix**: Implemented client-side and server-side request deduplication
* **Enhancement**: Added version tracking for deployment verification
* **Enhancement**: Improved error handling and user feedback messages
* **Dev**: Enhanced logging system for better troubleshooting

= 2.1.0 - AI Integration Major Release =
* **New**: OpenAI GPT-4o integration for title and alt text generation
* **New**: Google Gemini AI support as alternative provider
* **New**: AI Tools modal with real-time preview
* **New**: Context-aware generation with keyword support
* **New**: Bulk operations for processing multiple images
* **New**: Comprehensive settings page with AI configuration
* **Enhancement**: Smart duplicate detection and handling
* **Enhancement**: Professional prompt templates for better results

= 2.0.0 - Architecture Rebuild =
* **Major**: Complete codebase restructure with PSR-4 and Composer
* **New**: Modern WordPress coding standards implementation
* **New**: Extensible plugin architecture for future features
* **New**: Comprehensive logging and debugging system
* **New**: Translation-ready infrastructure
* **Enhancement**: Improved Media Library integration
* **Performance**: Modern JavaScript with jQuery optimization

= 1.0.0 - Initial Release =
* Basic image duplication functionality
* Media Library integration
* Toast notification system

== Upgrade Notice ==

= 2.2.0 =
Major performance improvements! Significantly faster Media Library experience with intelligent caching and modern DOM handling. Update recommended for all users.

= 2.1.2 =
Critical stability fix for AI generation. Resolves duplicate requests and flickering UI issues. Update strongly recommended.

= 2.1.0 =
Major feature release with full AI integration! OpenAI and Gemini support, bulk operations, and comprehensive AI tools. Backup before upgrading.

== FAQ ==

**Q: Do I need an API key to use this plugin?**
A: Yes, you need either an OpenAI API key or Google Gemini API key to use the AI features. The plugin provides clear setup instructions.

**Q: How much do the AI API calls cost?**
A: OpenAI GPT-4o costs approximately $0.01-0.03 per image for title and alt text generation. Gemini is generally less expensive. The plugin includes request deduplication to minimize costs.

**Q: Can I use this plugin without AI features?**
A: The plugin focuses on AI-powered metadata generation. While image duplication works without AI, the main value comes from the AI features.

**Q: Is my API key secure?**
A: Yes, API keys are stored in your WordPress database and never transmitted except to the respective AI services (OpenAI/Google). Follow WordPress security best practices.

**Q: Does this work with page builders?**
A: Yes, the plugin integrates with WordPress Media Library, which works with all major page builders including Elementor, Divi, and Gutenberg.

**Q: Can I customize the AI prompts?**
A: Yes, the settings page allows you to customize prompts for different types of content and adjust AI behavior to match your needs.

== License ==

This plugin is licensed under the GPLv2 or later.
