# AWS RDS Provisioning Boilerplate for Laravel (MySQL & PostgreSQL)

AWS RDS Laravel provisioning example, automation boilerplate, MySQL PostgreSQL template

This boilerplate project demonstrates how to automate provisioning, management, and monitoring of AWS RDS instances using Laravel. Perfect for developers seeking an extensible template for AWS RDS (MySQL & PostgreSQL) integration, automation, and best practices.

## Table of Contents

* [Features](#features)
* [Prerequisites](#prerequisites)
* [Project Initialization](#project-initialization)
* [Installation](#installation)
* [Configuration](#configuration)
* [Usage](#usage)
* [Extending for PostgreSQL](#extending-for-postgresql)
* [License](#license)

## Features

* **Provisioning**: Create AWS RDS instances with customizable database names, users, and passwords.
* **Multi-Engine Support**: Works out of the box with MySQL and PostgreSQL engines.
* **Status Tracking**: Automatically fetch and update instance status and endpoint information.
* **Soft Deletion**: Mark RDS instances for deletion in the application, with optional manual cleanup in the AWS Console.
* **Logging & Error Handling**: Comprehensive try-catch blocks and Laravel logging for easy debugging.

### Prerequisites

* PHP >= 8.0
* Laravel >= 12.x
* AWS SDK for PHP (installed via Composer)
* AWS credentials configured via `.env` or IAM role

### Project Initialization

If you’re starting from scratch with a new Laravel 12 project, you can scaffold the application using Composer:

```bash
composer create-project laravel/laravel aws-rds-laravel-example "^12.0"
cd aws-rds-laravel-example
```

Generate an application key:

```bash
php artisan key:generate
```

Install Passport or Sanctum (optional) for API authentication:

```bash
php artisan passport:install
# or
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/your-repo/aws-rds-laravel-example.git
   cd aws-rds-laravel-example
   ```

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Set up environment**
   Copy the example env file and fill in your AWS credentials and default database settings.

   ```bash
   cp .env.example .env
   ```

   ```dotenv
   AWS_ACCESS_KEY_ID=your-access-key
   AWS_SECRET_ACCESS_KEY=your-secret-key
   AWS_DEFAULT_REGION=us-east-1

   DB_CONNECTION=mysql        # or pgsql
   DB_HOST=127.0.0.1
   DB_PORT=3306               # 5432 for PostgreSQL
   DB_DATABASE=laravel
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Run migrations**

   ```bash
   php artisan migrate
   ```



### Usage

#### Create an RDS Instance

Make a POST request to the `/api/rds-instances` endpoint with the following payload:

```json
{
    "client_id": "client-xyz",
    "db_name": "my_database",
    "username": "db_user",
    "password": "securePassword123"
}
```

#### List All Instances

```bash
GET /api/rds-instances
```

#### View a Single Instance

```bash
GET /api/rds-instances/{id}
```

#### Mark for Deletion

```bash
DELETE /api/rds-instances/{id}
```

### Extending for PostgreSQL

To switch from MySQL to PostgreSQL, update the `AWS_RDS_ENGINE` in your `.env` and ensure the `DB_CONNECTION` is set to `pgsql`. The service class handles both engines transparently.

```dotenv
AWS_RDS_ENGINE=postgres
DB_CONNECTION=pgsql
DB_PORT=5432
```

### License

MIT License. See [LICENSE](LICENSE) for details.
