# Dashboard Reports System

A comprehensive web-based dashboard for managing and monitoring user and client reports with real-time data filtering, pagination, and CRUD operations.

## Features

**Core Functionality**
- **Dual Tab Interface**: Switch between User and Client reports
- **Advanced Filtering**: Search, status, and date range filters
- **Real-time Data**: Live API integration for up-to-date information
- **Pagination**: Efficient data browsing with page navigation
- **Export Capability**: CSV export for filtered data

  **User Management**
- View user session details and activities
- Monitor upload status (Done, Processing, Failed)
- Track campaign performance and agent activities
- File duration and size monitoring

  💼 **Client Management**
- Complete client information management
- Client validation status tracking
- Add new clients functionality
- Update client names (with restricted editing permissions)
- MAC address and host address tracking

  🛡️ **Security Features**
- User authentication system
- Session management
- Secure API communication
- Protected routes

## Technology Stack

- **Frontend**: HTML5, Tailwind CSS, JavaScript
- **Backend**: PHP
- **APIs**: RESTful JSON APIs
- **Icons**: Font Awesome 6.4.0
- **Styling**: Tailwind CSS framework

## Installation

  Prerequisites
- PHP 7.4 or higher
- Web server (Apache/Nginx)
- MySQL database (if required by APIs)

  Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone https://github.com/monish8978/genric-screen-recorder.git
   cd genric-screen-recorder
   ```

2. **Configure Environment**
   - Update API endpoints in configuration
   - Set up database connection if needed
   - Configure web server to point to project directory

3. **API Configuration**
   Update the following API endpoints in your configuration:
   ```php
   define('USER_API_URL', 'your-user-api-endpoint');
   define('CLIENT_API_URL', 'your-client-api-endpoint');
   define('CLIENT_API_URL_ADD', 'your-add-client-api-endpoint');
   define('CLIENT_API_URL_UPDATE', 'your-update-client-api-endpoint');
   ```

4. **Authentication Setup**
   - Ensure `includes/auth.php` is properly configured
   - Set up session management
   - Configure login/logout functionality

## File Structure

```
dashboard-reports-system/
├── index.php                 # Login page
├── dashboard.php            # Main dashboard
├── includes/
│   ├── auth.php            # Authentication functions
│   └── config.php          # Configuration settings
├── api/
│   └── export_csv.php      # CSV export functionality
├── assets/
│   └── css/               # Additional stylesheets
└── README.md              # This file
```

## API Integration

  Required Endpoints

1. **User Data API** (`USER_API_URL`)
   - Method: GET
   - Returns: User session data with pagination support

2. **Client Data API** (`CLIENT_API_URL`)
   - Method: GET
   - Returns: Client information with validation status

3. **Add Client API** (`CLIENT_API_URL_ADD`)
   - Method: POST
   - Accepts: Client data object

4. **Update Client API** (`CLIENT_API_URL_UPDATE`)
   - Method: POST
   - Accepts: Client ID and updated fields

  API Response Format
```json
{
  "status": 200,
  "message": "Success",
  "data": [...]
}
```

## Usage Guide

  📊 Viewing Reports

1. **Access Dashboard**
   - Login with credentials
   - Navigate to dashboard

2. **Switch Between Tabs**
   - Use sidebar to toggle between User and Client views

3. **Apply Filters**
   - Use search box for text filtering
   - Select status filters
   - Set date ranges for time-based filtering

  👥 Managing Clients

1. **View Clients**
   - Navigate to Client tab
   - View all client information in table format

2. **Add New Client**
   - Click "Add Client" button
   - Fill in required fields
   - Submit form

3. **Update Client Name**
   - Click "Update" button on client row
   - Edit client name in modal (only editable field)
   - Save changes

  📈 Exporting Data

1. **Apply desired filters**
2. **Click "Export CSV" button**
3. **Download filtered data in CSV format**

## Configuration

  Environment Variables
Set the following in your environment or configuration file:

```php
// API Endpoints
define('USER_API_URL', 'https://api.yoursite.com/users');
define('CLIENT_API_URL', 'https://api.yoursite.com/clients');
define('CLIENT_API_URL_ADD', 'https://api.yoursite.com/clients/add');
define('CLIENT_API_URL_UPDATE', 'https://api.yoursite.com/clients/update');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
```

  Security Settings
- Enable HTTPS in production
- Configure proper CORS headers
- Set secure session cookies
- Implement API rate limiting

## Customization

  Styling
- Modify Tailwind CSS classes in PHP files
- Update color scheme in configuration
- Add custom CSS in assets/css/

  Functionality
- Extend filter options
- Add new data columns
- Implement additional export formats
- Add real-time updates with WebSockets

## Troubleshooting

  Common Issues

1. **API Connection Failed**
   - Check API endpoints configuration
   - Verify network connectivity
   - Review CORS settings

2. **Authentication Issues**
   - Verify session configuration
   - Check login credentials
   - Review auth.php implementation

3. **Data Not Loading**
   - Check API response format
   - Verify data parsing logic
   - Review browser console for errors

  Debug Mode
Enable debug mode by uncommenting in dashboard.php:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Support

For support and questions:
- Create an issue in the GitHub repository
- Contact: your-email@example.com
- Documentation: [Wiki](https://github.com/your-username/dashboard-reports-system/wiki)

## Changelog

  Version 1.0.0
- Initial release
- User and Client management
- Advanced filtering system
- CSV export functionality
- Responsive dashboard design

---

**Note**: This system requires proper API endpoints to be configured for full functionality. Ensure all API integrations are properly set up before deployment.
