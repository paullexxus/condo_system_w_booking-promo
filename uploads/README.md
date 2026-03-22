# File Upload Structure & Management

## Folder Structure

```
uploads/
├── valid_ids/              # Valid ID documents for verification
│   ├── admin/             # Admin valid IDs
│   ├── host/              # Host/Manager valid IDs
│   └── renter/            # Renter valid IDs
│
├── unit_images/           # Unit property images
│   ├── overview/          # Overview/cover images
│   └── gallery/           # Unit gallery images
│
├── payment_proofs/        # Payment verification documents
│   ├── gcash/             # GCash payment proofs
│   ├── bank_transfer/     # Bank transfer receipts
│   ├── paypal/            # PayPal payment proofs
│   └── other/             # Other payment methods
│
├── profile_pictures/      # User profile pictures
└── documents/             # General documents
```

## Configuration

Import the file paths configuration in your PHP files:

```php
include_once '../config/file_paths.php';
```

## Available Constants

### Valid IDs Paths
```php
VALID_IDS_DIR              // Base valid IDs directory
VALID_IDS_URL              // Base valid IDs URL
VALID_IDS_ADMIN_DIR        // Admin IDs directory
VALID_IDS_ADMIN_URL        // Admin IDs URL
VALID_IDS_HOST_DIR         // Host IDs directory
VALID_IDS_HOST_URL         // Host IDs URL
VALID_IDS_RENTER_DIR       // Renter IDs directory
VALID_IDS_RENTER_URL       // Renter IDs URL
```

### Unit Images Paths
```php
UNIT_IMAGES_DIR            // Base unit images directory
UNIT_IMAGES_URL            // Base unit images URL
UNIT_OVERVIEW_DIR          // Overview images directory
UNIT_OVERVIEW_URL          // Overview images URL
UNIT_GALLERY_DIR           // Gallery images directory
UNIT_GALLERY_URL           // Gallery images URL
```

### Payment Proofs Paths
```php
PAYMENT_PROOFS_DIR         // Base payment proofs directory
PAYMENT_PROOFS_URL         // Base payment proofs URL
PAYMENT_GCASH_DIR          // GCash proofs directory
PAYMENT_GCASH_URL          // GCash proofs URL
PAYMENT_BANK_DIR           // Bank transfer proofs directory
PAYMENT_BANK_URL           // Bank transfer proofs URL
PAYMENT_PAYPAL_DIR         // PayPal proofs directory
PAYMENT_PAYPAL_URL         // PayPal proofs URL
PAYMENT_OTHER_DIR          // Other payment proofs directory
PAYMENT_OTHER_URL          // Other payment proofs URL
```

### Other Paths
```php
PROFILE_PICTURES_DIR       // Profile pictures directory
PROFILE_PICTURES_URL       // Profile pictures URL
DOCUMENTS_DIR              // General documents directory
DOCUMENTS_URL              // General documents URL
```

## Allowed Extensions & Sizes

```php
ALLOWED_IMAGE_EXTENSIONS   // ['jpg', 'jpeg', 'png', 'gif', 'webp']
ALLOWED_DOCUMENT_EXTENSIONS // ['pdf', 'doc', 'docx', 'xls', 'xlsx']
ALLOWED_ID_EXTENSIONS      // ['jpg', 'jpeg', 'png', 'pdf']

MAX_IMAGE_SIZE             // 5 MB
MAX_DOCUMENT_SIZE          // 10 MB
MAX_ID_SIZE                // 5 MB
```

## Usage Examples

### 1. Upload Valid ID During Registration

```php
include_once '../config/file_paths.php';

// Get upload directory based on user role
$target_dir = FileUploadHelper::getValidIDDirectory('host');

// Validate file
$validation = FileUploadHelper::validateFile(
    $_FILES['valid_id'],
    ALLOWED_ID_EXTENSIONS,
    MAX_ID_SIZE
);

if (!$validation['success']) {
    echo "Error: " . $validation['error'];
    exit;
}

// Save file
$user_id = $_SESSION['user_id'];
$result = FileUploadHelper::saveFile(
    $_FILES['valid_id'],
    $target_dir,
    'user_' . $user_id
);

if ($result['success']) {
    // Store filename in database
    $filename = $result['filename'];
    // Save to database...
    
    // Get full URL for display
    $file_url = FileUploadHelper::getFileURL(
        $filename,
        FileUploadHelper::getValidIDURL('host')
    );
} else {
    echo "Error: " . $result['error'];
}
```

### 2. Upload Unit Overview Image

```php
// Save overview image
$result = FileUploadHelper::saveFile(
    $_FILES['unit_overview'],
    UNIT_OVERVIEW_DIR,
    'unit_' . $unit_id
);

if ($result['success']) {
    $overview_filename = $result['filename'];
    // Save to database...
    
    $file_url = FileUploadHelper::getFileURL(
        $overview_filename,
        UNIT_OVERVIEW_URL
    );
}
```

### 3. Upload Payment Proof

```php
// Get directory based on payment method
$target_dir = FileUploadHelper::getPaymentProofDirectory('gcash');

// Validate
$validation = FileUploadHelper::validateFile(
    $_FILES['payment_proof'],
    ALLOWED_IMAGE_EXTENSIONS,
    MAX_IMAGE_SIZE
);

if ($validation['success']) {
    // Save file
    $result = FileUploadHelper::saveFile(
        $_FILES['payment_proof'],
        $target_dir,
        'payment_' . $payment_id
    );
    
    if ($result['success']) {
        $filename = $result['filename'];
        // Save to database...
        
        $file_url = FileUploadHelper::getFileURL(
            $filename,
            FileUploadHelper::getPaymentProofURL('gcash')
        );
    }
}
```

### 4. Delete File

```php
$file_path = PAYMENT_GCASH_DIR . '/' . $filename;
if (FileUploadHelper::deleteFile($file_path)) {
    echo "File deleted successfully";
    // Update database...
}
```

## Database Schema

Add these columns to relevant tables:

### users table
```sql
ALTER TABLE users ADD COLUMN valid_id1 VARCHAR(255);
ALTER TABLE users ADD COLUMN valid_id2 VARCHAR(255);
ALTER TABLE users ADD COLUMN id_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending';
```

### units table
```sql
ALTER TABLE units ADD COLUMN overview_image VARCHAR(255);
ALTER TABLE units ADD COLUMN gallery_images JSON;
```

### payments table
```sql
ALTER TABLE payments ADD COLUMN payment_proof VARCHAR(255);
ALTER TABLE payments ADD COLUMN proof_verified BOOLEAN DEFAULT FALSE;
```

### reservations table
```sql
ALTER TABLE reservations ADD COLUMN documents JSON;
```

## Security Notes

1. **File Validation**: Always validate file type and size
2. **Unique Filenames**: Use timestamps and random IDs to prevent overwrites
3. **Access Control**: Implement proper permission checks before serving files
4. **Virus Scanning**: Consider implementing virus scanning for production
5. **Backups**: Regularly backup uploaded files
6. **Cleanup**: Implement cleanup for orphaned files

## Filename Pattern

Generated filenames follow this pattern:
```
{prefix}_{original_filename}_{timestamp}_{unique_id}.{extension}
```

Example:
```
user_123_valid_id_20251113041530_a1b2c3d4.jpg
payment_456_receipt_20251113041545_e5f6g7h8.png
unit_789_overview_20251113041600_i9j0k1l2.jpg
```

This ensures:
- No filename collisions
- Easy identification of related files
- Sortable by timestamp
- Unique across the system
