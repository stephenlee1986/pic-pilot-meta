<?php
/**
 * Information and Guide Tab Content
 */

defined('ABSPATH') || exit;
?>

<div class="pic-pilot-info-content" style="max-width: 800px; margin-top: 20px;">
    
    <div class="postbox">
        <h2 class="hndle" style="padding: 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">
            🤖 What is Pic Pilot Meta?
        </h2>
        <div class="inside" style="padding: 20px;">
            <p style="font-size: 14px; line-height: 1.6;">
                <strong>Pic Pilot Meta</strong> is an AI-powered WordPress plugin that automatically generates high-quality metadata for your images, including alt text, titles, and smart filenames. It supports both OpenAI's GPT-4 Vision and Google's Gemini Pro Vision models to analyze your images and create SEO-friendly, accessible descriptions.
            </p>
            
            <h3 style="color: #2271b1; margin-top: 25px;">🎯 Core Features</h3>
            <ul style="line-height: 1.7;">
                <li><strong>AI Alt Text Generation:</strong> Automatically create descriptive alt text for accessibility and SEO</li>
                <li><strong>Smart Titles:</strong> Generate SEO-optimized image titles based on content</li>
                <li><strong>Smart Filename Generation:</strong> Create SEO-friendly filenames during duplication</li>
                <li><strong>Intelligent Duplication:</strong> Clone images with AI-generated metadata (always enabled)</li>
                <li><strong>Upload Auto-Generation:</strong> Automatically process new uploads with alt text, title, and filename (configurable in Settings)</li>
                <li><strong>Media Library Integration:</strong> Generate metadata directly from the PicPilot column (always visible)</li>
                <li><strong>Universal Page Builder Support:</strong> Works seamlessly with Elementor, Beaver Builder, Visual Composer, Divi, and more</li>
                <li><strong>Accessibility Dashboard:</strong> Comprehensive image audit and scanning capabilities</li>
                <li><strong>Broken Images Scanner:</strong> Detect and manage broken image links, missing files, and external images</li>
            </ul>
        </div>
    </div>

    <div class="postbox">
        <h2 class="hndle" style="padding: 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">
            📍 Where to Use Pic Pilot Meta
        </h2>
        <div class="inside" style="padding: 20px;">
            
            <h3 style="color: #2271b1;">✅ Supported Locations</h3>
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 8px;">📚 Media Library (List View)</h4>
                <p style="margin-left: 20px; color: #666;">
                    • Generate/Regenerate alt text and titles in dedicated PicPilot column<br>
                    • Filter images by alt text status<br>
                    • Duplicate images with AI metadata (smart duplication always available)<br>
                    • Use keywords for better context<br>
                    • Bulk generation with progress tracking
                </p>
                
                <h4 style="margin-bottom: 8px;">✏️ Attachment Edit Page</h4>
                <p style="margin-left: 20px; color: #666;">
                    • Full AI generation interface<br>
                    • Direct form field population<br>
                    • Keywords support for better results<br>
                    • Works from any link to attachment edit page
                </p>
                
                <h4 style="margin-bottom: 8px;">📤 Upload Processing</h4>
                <p style="margin-left: 20px; color: #666;">
                    • Automatic metadata generation on image upload (alt text, title, and filename)<br>
                    • Background processing for new images<br>
                    • Must be enabled in Settings → Behavior tab<br>
                    • Works during media upload and drag-and-drop
                </p>
                
                <h4 style="margin-bottom: 8px;">🏗️ Page Builder Modals</h4>
                <p style="margin-left: 20px; color: #666;">
                    • Universal modal system works with Elementor, Beaver Builder, Visual Composer, Divi<br>
                    • Full AI generation capabilities in modal interfaces<br>
                    • Automatic detection and button injection<br>
                    • Complete metadata generation without leaving the builder
                </p>
                
                <h4 style="margin-bottom: 8px;">📊 Accessibility Dashboard</h4>
                <p style="margin-left: 20px; color: #666;">
                    • Comprehensive image accessibility scanning<br>
                    • Professional reports and export capabilities<br>
                    • Priority-based issue identification<br>
                    • Works with any WordPress theme or page builder
                </p>

                <h4 style="margin-bottom: 8px;">🔍 Broken Images Scanner</h4>
                <p style="margin-left: 20px; color: #666;">
                    • Dedicated tab for broken image detection<br>
                    • Finds missing files, broken links, and external images<br>
                    • Real-time progress tracking with batch processing<br>
                    • CSV export for reporting and management
                </p>
            </div>

            <h3 style="color: #d63638;">⚠️ Current Limitations</h3>
            <div style="background: #fff2cd; padding: 15px; border-left: 4px solid #ffb900; margin-bottom: 15px;">
                <h4 style="margin-top: 0; color: #8a6914;">📁 Bulk Filename Generation</h4>
                <p style="margin-bottom: 0; color: #8a6914;">
                    <strong>Filename generation is not available in bulk operations.</strong><br>
                    Changing filenames in bulk could disconnect images from their references in posts, pages, and other content. Filename generation is only available for individual images and duplication operations.
                </p>
            </div>
            <div style="background: #fff2cd; padding: 15px; border-left: 4px solid #ffb900; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: #8a6914;">🎨 SVG File Support</h4>
                <p style="margin-bottom: 0; color: #8a6914;">
                    <strong>SVG files are not currently supported for accessibility scanning or AI generation.</strong><br>
                    WordPress's core image detection excludes SVG files by default, requiring significant custom implementation. SVG support is planned for a future update.
                </p>
            </div>
        </div>
    </div>

    <div class="postbox">
        <h2 class="hndle" style="padding: 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">
            🚀 How to Use
        </h2>
        <div class="inside" style="padding: 20px;">
            
            <h3 style="color: #2271b1;">1️⃣ Media Library Workflow</h3>
            <ol style="line-height: 1.7;">
                <li>Go to <strong>Media → Library</strong></li>
                <li>Switch to <strong>List View</strong> (if in grid view)</li>
                <li>Use the <strong>PicPilot Tools</strong> column (always visible)</li>
                <li>Use the filter dropdown to show "Images without Alt Text"</li>
                <li>Click <strong>"Generate Alt Text"</strong> or <strong>"Generate Title"</strong> buttons</li>
                <li>Optionally add keywords in the input field for better context</li>
            </ol>

            <h3 style="color: #2271b1;">2️⃣ Individual Image Editing</h3>
            <ol style="line-height: 1.7;">
                <li>Click on any image in the media library</li>
                <li>Click <strong>"Edit more details"</strong> or navigate to the attachment edit page</li>
                <li>Use the AI generation section at the top of the page</li>
                <li>Add keywords for better context (recommended)</li>
                <li>Click generate buttons and watch fields populate automatically</li>
            </ol>

            <h3 style="color: #2271b1;">3️⃣ Smart Duplication (Always Available)</h3>
            <ol style="line-height: 1.7;">
                <li>From media library list view, click <strong>"Duplicate"</strong></li>
                <li>Smart duplication modal opens with AI generation options</li>
                <li>Choose manual entry or AI generation for title, alt text, and filename</li>
                <li>Use keywords to provide context for AI generation</li>
                <li>The new image copy will have the generated metadata</li>
            </ol>

            <h3 style="color: #2271b1;">4️⃣ Bulk Generation</h3>
            <ol style="line-height: 1.7;">
                <li>From media library list view, select multiple images using checkboxes</li>
                <li>Choose <strong>"Generate AI Metadata"</strong> from the bulk actions dropdown</li>
                <li>Select which metadata types to generate (title and/or alt text)</li>
                <li>Click <strong>"Start Generation"</strong> and monitor the progress</li>
            </ol>

            <h3 style="color: #2271b1;">5️⃣ Quick Bulk Generation Buttons</h3>
            <ol style="line-height: 1.7;">
                <li>Go to <strong>Media → Library</strong> and switch to list view</li>
                <li>Use the quick generation buttons next to the filter dropdown:</li>
                <li><strong>"🤖 Generate for Missing Alt Text"</strong> - Finds and processes all images without alt text</li>
                <li><strong>"🤖 Generate for Missing Titles"</strong> - Finds and processes all images with default or generic titles</li>
                <li>Monitor the progress as each image is processed automatically</li>
            </ol>
            
            <h3 style="color: #2271b1;">6️⃣ Page Builder Usage</h3>
            <ol style="line-height: 1.7;">
                <li>Open any page builder (Elementor, Beaver Builder, Visual Composer, Divi)</li>
                <li>Access media selection in any widget or element</li>
                <li>Look for the blue <strong>"Pic Pilot"</strong> button in the media modal</li>
                <li>Click to open the full AI generation modal</li>
                <li>Generate alt text, titles, and duplicate images without leaving the builder</li>
            </ol>
            
            <h3 style="color: #2271b1;">7️⃣ Accessibility Dashboard</h3>
            <ol style="line-height: 1.7;">
                <li>Go to <strong>Pic Pilot Meta → Dashboard</strong></li>
                <li>Click <strong>"Start New Scan"</strong> to begin accessibility audit</li>
                <li>Review scan results with priority-based filtering</li>
                <li>Use <strong>"Fix Now"</strong> buttons for quick repairs</li>
                <li>Export comprehensive reports as CSV</li>
            </ol>

            <h3 style="color: #2271b1;">8️⃣ Broken Images Scanner</h3>
            <ol style="line-height: 1.7;">
                <li>Go to <strong>Pic Pilot Meta → Dashboard</strong></li>
                <li>Click the <strong>"Broken Images"</strong> tab</li>
                <li>Click <strong>"Start Broken Images Scan"</strong> to begin detection</li>
                <li>Review results: missing files, broken links, external images</li>
                <li>Use action buttons to edit posts/media or export CSV reports</li>
            </ol>

            <h3 style="color: #2271b1;">9️⃣ Upload Auto-Generation Setup</h3>
            <ol style="line-height: 1.7;">
                <li>Go to <strong>Pic Pilot Meta → Settings</strong></li>
                <li>Click the <strong>"Behavior"</strong> tab</li>
                <li>Enable <strong>"Auto-generate on Upload"</strong></li>
                <li>Select which metadata to generate: alt text, title, and/or filename</li>
                <li>Now all new image uploads will automatically get AI-generated metadata</li>
            </ol>
        </div>
    </div>

    <div class="postbox">
        <h2 class="hndle" style="padding: 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">
            💡 Best Practices & Tips
        </h2>
        <div class="inside" style="padding: 20px;">
            
            <h3 style="color: #2271b1;">🎯 Keywords for Better Results</h3>
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                <p style="margin: 0; line-height: 1.6;">
                    <strong>Always use keywords!</strong> Provide context about the person, profession, setting, or purpose.<br>
                    <strong>Examples:</strong> "Business manager", "Construction site", "Medical professional", "Corporate headshot"
                </p>
            </div>

            <h3 style="color: #2271b1;">⚡ Efficiency Tips</h3>
            <ul style="line-height: 1.7;">
                <li><strong>Use filters:</strong> Filter by "Images without Alt Text" to focus on images that need attention</li>
                <li><strong>Batch processing:</strong> Enable auto-generation on upload for new images</li>
                <li><strong>Bulk operations:</strong> Use bulk generation for selected images, or quick buttons for comprehensive coverage</li>
                <li><strong>Smart detection:</strong> The "Missing Titles" button detects generic titles like "IMG_001" or filenames used as titles</li>
                <li><strong>Fallback handling:</strong> The plugin automatically provides fallback descriptions when AI refuses to identify people</li>
                <li><strong>Review generated content:</strong> Always review AI-generated content for accuracy and brand alignment</li>
            </ul>

            <h3 style="color: #2271b1;">🎨 SEO & Accessibility</h3>
            <ul style="line-height: 1.7;">
                <li><strong>Alt text:</strong> Focuses on accessibility and describes what's visible in the image</li>
                <li><strong>Titles:</strong> Optimized for SEO and search engine discovery</li>
                <li><strong>Filenames:</strong> Clean, SEO-friendly naming for better organization</li>
            </ul>

            <h3 style="color: #2271b1;">💰 Cost Management</h3>
            <div style="background: #fff2cd; padding: 15px; border-left: 4px solid #ffb900;">
                <p style="margin: 0; line-height: 1.6;">
                    <strong>Remember:</strong> Each AI generation uses API credits from your chosen provider (OpenAI or Gemini). Use keywords to get better results on the first try and avoid regenerating content unnecessarily.
                </p>
            </div>
        </div>
    </div>

    <div class="postbox">
        <h2 class="hndle" style="padding: 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">
            🛠️ Troubleshooting
        </h2>
        <div class="inside" style="padding: 20px;">
            
            <h3 style="color: #2271b1;">🔧 Common Issues</h3>
            
            <h4>🚫 "AI refused to identify person" message</h4>
            <p style="margin-left: 20px; color: #666; line-height: 1.6;">
                This is normal! OpenAI's safety guidelines prevent identifying specific people. The plugin automatically provides a fallback description based on your keywords.
            </p>

            <h4>❌ Generation not working</h4>
            <ul style="margin-left: 20px; color: #666; line-height: 1.6;">
                <li>Check that your AI provider and API key are correctly entered in Settings</li>
                <li>Ensure you have API credits remaining in your chosen provider account (OpenAI or Google Cloud)</li>
                <li>Enable logging in Settings to see detailed error messages</li>
            </ul>

            <h4>🔍 Can't find Pic Pilot button in page builders</h4>
            <p style="margin-left: 20px; color: #666; line-height: 1.6;">
                The blue "Pic Pilot" button should appear automatically in media modals. If not visible, check that your browser allows JavaScript and try refreshing the page builder interface.
            </p>

            <h3 style="color: #2271b1;">📞 Need Help?</h3>
            <p style="line-height: 1.6;">
                Enable <strong>AI Logging</strong> in the Settings tab to get detailed information about what's happening with your generations. This helps identify any issues with your API key, credits, or specific images.
            </p>
        </div>
    </div>

</div>

<style>
.pic-pilot-meta .pic-pilot-info-content .postbox {
    margin-bottom: 20px;
    border: 1px solid #ddd;
}

.pic-pilot-meta .pic-pilot-info-content .postbox .hndle {
    font-size: 16px;
    font-weight: 600;
}

.pic-pilot-meta .pic-pilot-info-content h3 {
    font-size: 15px;
    margin-bottom: 10px;
}

.pic-pilot-meta .pic-pilot-info-content h4 {
    font-size: 14px;
    margin-bottom: 5px;
    color: #333;
}

.pic-pilot-meta .tab-content {
    margin-top: 20px;
}
</style>