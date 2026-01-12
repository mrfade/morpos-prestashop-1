# MorPOS for PrestaShop

[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7%2B-DF0067.svg)](https://www.prestashop.com/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**MorPOS for PrestaShop** is a secure and easy-to-use payment gateway module that integrates the **Morpara MorPOS** payment system with PrestaShop stores. Customers are redirected through a secure **Hosted Payment Page (HPP)** flow or can use an **Embedded Payment Form** when completing their orders.

![MorPOS Payment Gateway](modules/morposgateway/views/img/morpos-logo.png)

## ✨ Features

- 🛒 **PrestaShop Integration**: Seamlessly adds MorPOS as a payment method 
- 🔒 **Secure Payments**: Hosted Payment Page (HPP) for maximum security
- 🎨 **Flexible Payment Forms**: Choose between Hosted or Embedded payment interfaces
- 🌍 **Multi-Currency**: Supports TRY, USD, EUR, GBP currencies
- 💳 **Multiple Payment Options**: Credit cards, debit cards, and installment payments
- 🔄 **Smart Retry System**: Automatic payment retry mechanism for failed transactions
- 🧪 **Sandbox Mode**: Test environment for development
- 🔧 **Easy Configuration**: Simple admin panel setup with connection testing
- 🛡️ **Security Features**: TLS 1.2+ requirement, signed API communication
- 📱 **Multi-Store Support**: Compatible with PrestaShop's multi-store feature

## 📋 Requirements

### Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **PrestaShop** | 1.7.0 | 8.2+ |
| **PHP** | 7.4 | 8.2+ |
| **TLS** | 1.2 | 1.3 |

### PHP Extensions

- `cURL` - Required for API communication
- `json` - Required for data processing
- `hash` - Required for security signatures
- `openssl` - Required for secure connections

### PrestaShop Features

- **SSL Certificate**: Strongly recommended for production environments
- **URL Rewriting**: Required for payment callbacks
- **Supported Currencies**: At least one of TRY, USD, EUR, or GBP must be enabled

## 🚀 Installation

### Method 1: PrestaShop Module Manager (Recommended)

1. **Download the Module**
   - Download the latest release ZIP file from [GitHub Releases](https://github.com/morpara/morpos-prestashop/releases)

2. **Upload via Admin Panel**
   - Go to PrestaShop admin → **Modules** → **Module Manager**
   - Click **Upload a Module**
   - Drag and drop or select the ZIP file
   - Click **Configure** after installation

### Method 2: Manual Installation (Developers)

1. **Download the Module**
   ```bash
   git clone https://github.com/morpara/morpos-prestashop.git
   ```

2. **Upload to PrestaShop**
   ```bash
   cp -r morpos-prestashop/modules/morposgateway/ /path/to/prestashop/modules/
   ```

3. **Install the Module**
   - Go to PrestaShop admin → **Modules** → **Module Manager**
   - Search for "MorPOS"
   - Click **Install**
   - Click **Configure** after installation

### Method 3: FTP/SSH Upload

1. Extract the module ZIP file
2. Upload the `morposgateway` folder to `/modules/` directory
3. Go to PrestaShop admin → **Modules** → **Module Manager**
4. Search for "MorPOS Payment Plugin"
5. Click **Install** → **Configure**

## ⚙️ Configuration

### 1. Basic Setup

Navigate to **Modules** → **Module Manager** → Search for **"MorPOS"** → Click **Configure**

### 2. Required Settings

Fill in the following mandatory fields:

| Field | Description | Example |
|-------|-------------|---------|
| **Merchant ID** | Your unique merchant identifier | `12345` |
| **Client ID** | OAuth client identifier | `your_client_id` |
| **Client Secret** | OAuth client secret | `your_client_secret` |
| **API Key** | Authentication key for API requests | `your_api_key` |

### 3. Environment Settings

- **Status**: Enable or disable the payment module
- **Test Mode**: Enable for development/testing
  - Uses sandbox endpoints
  - No real transactions processed
  - Test card numbers accepted

- **Form Type**: Choose payment interface
  - `Hosted`: Redirect to MorPOS payment page (recommended)
  - `Embedded`: Payment form within your checkout page

### 4. Order Status Settings

Configure order statuses for different payment outcomes:
- **Success Status**: Order status when payment succeeds (default: Payment Accepted)
- **Failed Status**: Order status when payment fails (default: Payment Error)

### 5. Connection Test

After entering credentials:
1. Click **Test Connection** button
2. Verify green checkmark appears ("Connection Successful")
3. Review system requirements status below the form

The system checks:
- ✅ PHP version compatibility (7.4+)
- ✅ PrestaShop version compatibility (1.7+)
- ✅ TLS version support (1.2+)
- ✅ Required PHP extensions (cURL, JSON, OpenSSL, Hash)

## 🛠️ Development & Debugging

### Logging

Enable debug mode in PrestaShop by editing `/config/defines.inc.php`:

```php
define('_PS_MODE_DEV_', true);
```

Logs will be written to `/var/logs/` directory in your PrestaShop installation.

### Database Tables

The module creates a custom table for tracking payment attempts:
- `ps_morpos_conversation_attempt` - Stores conversation IDs and payment retry data

### Payment Flow

1. **Initial Order Creation**: Order is created with "Awaiting Payment" status
2. **Payment Redirect**: Customer is redirected to MorPOS payment page
3. **Payment Processing**: Customer completes payment on MorPOS
4. **Callback Handling**: MorPOS sends payment result to callback URL
5. **Order Update**: Order status is updated based on payment result
6. **Retry Mechanism**: Failed payments can be retried with the same order ID

## 🔍 Troubleshooting

### Common Issues

#### "An error occurred while initiating the payment." Error

This typically indicates configuration issues:

1. **Check Credentials**: Verify all API credentials are correct
2. **Test Connection**: Use the connection test feature in module configuration
3. **Check Requirements**: Ensure server meets minimum requirements
4. **SSL/TLS**: Verify TLS 1.2+ is supported
5. **Time Sync**: Ensure server time is accurate
6. **Permissions**: Verify PHP has write access to `/var/logs/` directory

#### Payment Not Processing

1. **Currency Support**: Ensure currency is supported (TRY, USD, EUR, GBP)
2. **Amount Limits**: Check minimum/maximum transaction limits
3. **Network**: Verify outbound HTTPS connections are allowed
4. **Logs**: Check PrestaShop logs in `/var/logs/` for API errors
5. **Module Status**: Ensure module is enabled in configuration

#### Checkout Page Issues

1. **URL Rewriting**: Ensure friendly URLs are enabled in PrestaShop
2. **Multi-Store**: Verify module is configured for active shop context
3. **Conflicts**: Temporarily disable other payment modules to test
4. **Theme**: Test with default PrestaShop theme
5. **Cache**: Clear PrestaShop cache (Advanced Parameters → Performance)

#### Callback/Return URL Issues

1. **Firewall**: Ensure callback URLs are not blocked by firewall
2. **SSL**: Verify SSL certificate is valid and not self-signed
3. **URL Format**: Callback URLs must be publicly accessible
4. **Payment Method**: Check if payment method is enabled for customer's currency

### System Requirements Check

The module includes a built-in system requirements checker accessible from the configuration page. It validates:

- ✅ PHP version compatibility (7.4+)
- ✅ PrestaShop version compatibility (1.7+)
- ✅ TLS version support (1.2+)
- ✅ Required PHP extensions (cURL, JSON, OpenSSL, Hash)

### Debug Mode

Enable detailed logging:

```php
// config/defines.inc.php
define('_PS_MODE_DEV_', true);

// Check logs at: var/logs/
```

### Database Issues

If payment tracking table is corrupted or missing:

```sql
-- Recreate the conversation attempt table
DROP TABLE IF EXISTS ps_morpos_conversation_attempt;

CREATE TABLE ps_morpos_conversation_attempt (
    id INT(11) NOT NULL AUTO_INCREMENT,
    order_id INT(11) NOT NULL,
    attempt_seq INT(11) NOT NULL DEFAULT 0,
    conversation_id VARCHAR(64) NOT NULL,
    data TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY order_conversation_unique (order_id, conversation_id),
    KEY order_id_idx (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🌐 Internationalization

The module supports multiple languages:

- **Turkish (tr_TR)**: Full translation available
- **English (en_US)**: Default language

### Adding Translations

PrestaShop handles translations through its built-in system. To add new translations:

1. **Export Translation Files**
   - Go to **International** → **Translations**
   - Select "Installed module translations"
   - Choose your language
   - Find "MorPOS Payment Plugin" and translate

2. **Manual Translation** (for developers)
   - Translation files are located in `/modules/morposgateway/translations/`
   - English: `en.php`
   - Turkish: `tr.php`
   - Turkish (formal): `tr-TR/` directory with XLIFF files

3. **Submit Translations**
   - Fork the repository
   - Add your translation files
   - Submit via GitHub pull requests

## 🤝 Contributing

We welcome contributions! Here's how to get started:

### Development Setup

1. **Fork the Repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/morpos-prestashop.git
   cd morpos-prestashop
   ```

2. **Set Up Local PrestaShop**
   - Install PrestaShop locally (version 1.7+)
   - Copy module to `modules/` directory:
     ```bash
     cp -r morpos-prestashop/modules/morposgateway/ /path/to/prestashop/modules/
     ```
   - Install module from PrestaShop admin panel

3. **Make Changes**
   - Follow PrestaShop coding standards
   - Add appropriate documentation
   - Test with different PrestaShop versions (1.7.x and 8.x)
   - Test both Hosted and Embedded payment forms

4. **Submit Pull Request**
   - Create feature branch: `git checkout -b feature/your-feature`
   - Commit changes: `git commit -m "Add your feature"`
   - Push branch: `git push origin feature/your-feature`
   - Open pull request on GitHub

### Coding Standards

- Follow [PrestaShop Coding Standards](https://devdocs.prestashop.com/1.7/development/coding-standards/)
- Use meaningful variable names and comments
- Test compatibility with supported PrestaShop versions
- Include PHPDoc comments for functions and classes
- Validate with PrestaShop Validator before submitting

### Testing Checklist

Before submitting:
- ✅ Module installs without errors
- ✅ Module uninstalls cleanly
- ✅ Payment works with both Hosted and Embedded forms
- ✅ Test mode functions correctly
- ✅ Connection test validates credentials
- ✅ Payment retry mechanism works
- ✅ All supported currencies work (TRY, USD, EUR, GBP)
- ✅ Multi-store compatibility (if applicable)
- ✅ Translations load correctly

## 📄 License

This project is licensed under the **MIT** License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: Check this README and inline code comments
- **Issues**: [GitHub Issues](https://github.com/morpara/morpos-prestashop/issues)
- **PrestaShop Forum**: [PrestaShop Addons](https://addons.prestashop.com/)
- **Contact**: [Morpara Support](https://morpara.com/support)

## 🙏 Acknowledgments

- **PrestaShop Team** - For the excellent e-commerce platform
- **PrestaShop Community** - For the robust ecosystem and support
- **Morpara** - For the secure payment infrastructure

---

**Made with ❤️ by [Morpara](https://morpara.com/)**

For more information about MorPOS payment solutions, visit [morpara.com](https://morpara.com/).