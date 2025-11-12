# Announcement File Upload Feature

## Overview
The announcements system now supports **image and file uploads** for announcements, guidelines, reminders, and alerts. Team officers can attach visual content and documents to enhance their communications.

## 🎯 Features Added

### 1. **Image Upload**
Upload multiple images to announcements for visual communication.

#### Supported Formats:
- JPG/JPEG
- PNG
- GIF
- WebP
- SVG

#### Features:
- **Multiple Upload**: Upload up to multiple images at once
- **Real-time Preview**: See thumbnails before posting
- **Responsive Grid**: Images display in 1, 2, or 3 columns based on count
- **Click to Enlarge**: Full-screen image viewer
- **Hover Effects**: Smooth zoom effects
- **Limit Display**: Shows up to 6 images in feed (+ count indicator)

### 2. **File Attachments**
Attach documents and files for download.

#### Supported Formats:
- **Documents**: PDF, DOC, DOCX, TXT
- **Spreadsheets**: XLS, XLSX
- **Presentations**: PPT, PPTX
- **Archives**: ZIP, RAR

#### Features:
- **Multiple Upload**: Attach multiple files
- **File Preview**: Shows filename and size before posting
- **Icon-based Display**: Different icons for each file type
- **Download Links**: One-click download for users
- **File Information**: Shows original filename and size in KB
- **New Tab Opening**: Files open in new tab for preview

## 📋 Database Changes

### Updated Table: `announcements`
```sql
ALTER TABLE announcements 
ADD COLUMN images JSON AFTER is_pinned,
ADD COLUMN attachments JSON AFTER images;
```

### Data Structure

#### Images (JSON Array):
```json
[
  "uploads/announcements/images/announcement_img_abc123.jpg",
  "uploads/announcements/images/announcement_img_def456.png"
]
```

#### Attachments (JSON Array of Objects):
```json
[
  {
    "path": "uploads/announcements/files/announcement_file_xyz789.pdf",
    "original_name": "Guidelines_2025.pdf",
    "size": 245760,
    "type": "pdf"
  }
]
```

## 📁 File Storage

### Directory Structure:
```
uploads/
└── announcements/
    ├── images/
    │   ├── announcement_img_[unique_id].jpg
    │   ├── announcement_img_[unique_id].png
    │   └── ...
    └── files/
        ├── announcement_file_[unique_id].pdf
        ├── announcement_file_[unique_id].docx
        └── ...
```

### File Naming:
- **Images**: `announcement_img_[unique_id].[extension]`
- **Files**: `announcement_file_[unique_id].[extension]`
- Unique IDs generated using `uniqid()` with more_entropy=true

## 🎨 User Interface

### Upload Modal (Create/Edit)

```
┌─────────────────────────────────────────┐
│ 📝 Create New Announcement              │
├─────────────────────────────────────────┤
│ Title: [________________________]       │
│ Content: [                              │
│          ________________________       │
│         ]                                │
│                                          │
│ 📷 Upload Images (Optional)             │
│ [Choose Files]                           │
│ 📄 JPG, PNG, GIF, WebP, SVG allowed    │
│ [Preview: 🖼️ 🖼️ 🖼️]                    │
│                                          │
│ 📎 Attach Files (Optional)              │
│ [Choose Files]                           │
│ 📄 PDF, Word, Excel, PPT, TXT, ZIP      │
│ [Preview: 📄 document.pdf (120 KB)]    │
│                                          │
│ [Cancel] [💾 Save Announcement]         │
└─────────────────────────────────────────┘
```

### Display in Feed

#### With Images:
```
┌─────────────────────────────────────────┐
│ 👤 Officer Name          [Badge]        │
│    Posted: Jan 15, 2025                 │
├─────────────────────────────────────────┤
│ Title of Announcement                   │
│ Content description...                  │
│                                          │
│ [Image Grid]                            │
│ ┌────┐ ┌────┐ ┌────┐                   │
│ │ 🖼️ │ │ 🖼️ │ │ 🖼️ │                   │
│ └────┘ └────┘ └────┘                   │
│                                          │
│ 📎 Attachments:                         │
│ ┌─────────────────────────────┐        │
│ │ 📄 Guidelines.pdf  [⬇️]     │        │
│ │ 📊 Report.xlsx     [⬇️]     │        │
│ └─────────────────────────────┘        │
│                                          │
│ ❤️ 5  💬 3  📤 2  🔖              │
└─────────────────────────────────────────┘
```

## 🔧 Technical Implementation

### Server-Side (PHP)

#### Create Announcement with Files:
```php
// Handle image uploads
$uploaded_images = [];
if (!empty($_FILES['images']['name'][0])) {
    $upload_dir = '../uploads/announcements/images/';
    // Process each image
    // Validate extension
    // Generate unique filename
    // Move uploaded file
    $uploaded_images[] = 'path/to/image';
}

// Handle file attachments
$uploaded_attachments = [];
if (!empty($_FILES['attachments']['name'][0])) {
    $upload_dir = '../uploads/announcements/files/';
    // Process each file
    // Store metadata
    $uploaded_attachments[] = [
        'path' => 'path/to/file',
        'original_name' => 'filename.pdf',
        'size' => filesize,
        'type' => 'pdf'
    ];
}

// Save to database as JSON
$images_json = json_encode($uploaded_images);
$attachments_json = json_encode($uploaded_attachments);
```

### Client-Side (JavaScript)

#### File Upload with Preview:
```javascript
// Image preview
document.getElementById('images').addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            // Display thumbnail
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});

// Submit with FormData
const formData = new FormData();
// Add all form fields
// Add image files
const imageFiles = document.getElementById('images').files;
for (let i = 0; i < imageFiles.length; i++) {
    formData.append('images[]', imageFiles[i]);
}
// Add attachment files
const attachmentFiles = document.getElementById('attachments').files;
for (let i = 0; i < attachmentFiles.length; i++) {
    formData.append('attachments[]', attachmentFiles[i]);
}

fetch(url, { method: 'POST', body: formData });
```

## 📊 Display Features

### Image Display:
- **1 Image**: Full width (12 columns)
- **2 Images**: Side by side (6 columns each)
- **3+ Images**: Grid of 3 columns (4 columns each)
- **6+ Images**: Shows first 6 with "+ X more" indicator
- **Click to Enlarge**: Opens full-screen modal viewer

### Attachment Display:
- List group with icon-based display
- File type icons:
  - 📄 PDF → `bi-file-earmark-pdf`
  - 📝 Word → `bi-file-earmark-word`
  - 📊 Excel → `bi-file-earmark-excel`
  - 📊 PowerPoint → `bi-file-earmark-ppt`
  - 🗜️ Archive → `bi-file-earmark-zip`
  - 📄 Text → `bi-file-earmark-text`
- Hover effects with left border highlight
- Download icon on right

## 🎯 Use Cases

### 1. Visual Announcements
**Scenario**: Promoting community event
- Upload event flyer as image
- Add details in content
- Users see visual immediately

### 2. Policy Guidelines with Documents
**Scenario**: New community policy
- Write summary in content
- Attach full policy PDF
- Users can download and read

### 3. Reminders with Supporting Materials
**Scenario**: Monthly meeting reminder
- Write reminder message
- Attach agenda PDF
- Attach previous meeting minutes

### 4. Training Materials
**Scenario**: Team officer training
- Upload training presentation (PPT)
- Attach reference documents
- Include training video screenshots

## 🔒 Security Features

### File Validation:
✅ Extension whitelist (only allowed types)
✅ File type verification
✅ Unique filename generation (prevents overwriting)
✅ Directory traversal prevention
✅ Size limits (enforced by server configuration)

### Upload Security:
- Files stored outside web root (where possible)
- Unique filenames prevent guessing
- Access controlled through PHP (can add auth layer)
- Malicious file detection (extension verification)

## 📱 Responsive Design

### Desktop:
- Images in multi-column grid
- Full attachment list
- Large previews in modal

### Tablet:
- Adjusted column widths
- Responsive image grid
- Touch-friendly buttons

### Mobile:
- Single column images
- Stacked attachments
- Full-width modals

## 🎨 Styling Details

### Image Styling:
```css
.announcement-images img {
    max-height: 300px;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.2s;
}

.announcement-images img:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
```

### Attachment Styling:
```css
.announcement-attachments .list-group-item {
    border-left: 3px solid #0d6efd;
}

.announcement-attachments .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0a58ca;
}
```

### Preview Styling:
```css
#image-preview img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}
```

## 📈 Benefits

### For Team Officers:
✅ **Visual Communication**: Images enhance message clarity
✅ **Document Sharing**: Easy distribution of files
✅ **Professional**: Polished announcements with media
✅ **Efficient**: No need for external file sharing
✅ **Organized**: All materials in one place

### For Community Members:
✅ **Better Understanding**: Visual aids help comprehension
✅ **Easy Access**: Download documents directly
✅ **No External Links**: Everything in one platform
✅ **Mobile Friendly**: View images and download files on any device

### For the Platform:
✅ **Complete Solution**: No reliance on third-party services
✅ **Better Engagement**: Rich media increases interaction
✅ **Professional Image**: Enterprise-level features
✅ **Data Ownership**: All content stored locally

## 🔄 Update Process

When editing announcements:
1. Existing files are preserved
2. New uploads are added to existing arrays
3. Future enhancement: Allow deletion of specific files
4. All changes tracked via updated_at timestamp

## 🚀 Future Enhancements (Possible)

1. **File Management**:
   - Delete specific images/attachments
   - Reorder images
   - Set featured image

2. **Advanced Features**:
   - Image cropping/editing
   - File version control
   - Bulk upload
   - Drag-and-drop interface

3. **Media Library**:
   - Reusable image library
   - Recently uploaded files
   - Search uploaded files

4. **Analytics**:
   - Track file downloads
   - View counts for images
   - Popular content metrics

5. **Extended Support**:
   - Video uploads
   - Audio files
   - Embedded content

## 📝 Summary

The announcement system now has **complete multimedia support** with:
- ✅ Image uploads (multiple formats)
- ✅ File attachments (documents, archives)
- ✅ Real-time previews
- ✅ Beautiful display in feed
- ✅ Full-screen image viewer
- ✅ Download links for files
- ✅ Responsive design
- ✅ Security measures

This makes the platform a complete communication solution for team officers! 🎉

---

**Status**: ✅ Fully Implemented
**Version**: 3.0 (Multimedia Edition)
**Last Updated**: October 13, 2025

