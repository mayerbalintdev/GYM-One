
---

# GYM One - Open Source Gym Management Software
<p align="center"><img src="https://gymoneglobal.com/assets/img/text-color-logo.png" alt="project-image"></p>


Welcome to GYM One! This open-source gym management software is designed to help fitness centers, personal trainers, and sports clubs streamline their operations. With its user-friendly interface and powerful features, GYM One makes managing your gym simpler and more efficient.

## Table of Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Usage](#usage)
6. [Admin Panel](#admin-panel)
7. [Database Structure](#database-structure)
8. [Contribution](#contribution)
9. [License](#license)
10. [Contact](#contact)

## Features

- **Member Management:** Easily add, edit, and remove members. Track their membership status, expiration dates, and attendance records.
- **Ticketing System:** Manage different types of tickets (e.g., day passes, monthly memberships) with varying prices and benefits.
- **Class Scheduling:** Create and manage class schedules. Allow members to sign up for classes online.
- **Payment Tracking:** Keep track of payments made by members. Generate reports for financial analysis.
- **Admin Panel:** A dedicated area for administrators to manage the entire system, including member and ticket management.
- **Responsive Design:** Built with Bootstrap for a seamless experience on desktops, tablets, and mobile devices.
- **Customizable:** Easily modify the code and design to fit your gym's branding and needs.

## Requirements

To run GYM One, ensure that you have the following:

- PHP version 7.4 or higher
- MySQL version 5.7 or higher
- A web server (Apache, Nginx, etc.)
- Composer for dependency management
- Bootstrap for styling (included)

## Installation

Follow these steps to install GYM One on your server:

1. **Download the Source Code:**
   You can clone the repository using Git:
   ```bash
   git clone https://github.com/mayerbalintdev/GYM-One.git
   cd gym-one
   ```

2. **Install Dependencies:**
   Make sure you have Composer installed, then run:
   ```bash
   composer install
   ```

3. **Set Up the Database:**
   Create a new MySQL database. You can use the following command in your MySQL shell:
   ```sql
   CREATE DATABASE gym_one;
   ```

4. **Import the Database Schema:**
   Import the `schema.sql` file located in the `database` directory:
   ```bash
   mysql -u username -p gym_one < database/schema.sql
   ```

5. **Configure Database Connection:**
   Open the `.env` file and configure your database connection:
   ```bash
   DB_SERVER= [Server IP]
   DB_USERNAME= [Admin Username]
   DB_PASSWORD= [Admin Password]
   DB_NAME=gym_one
   ```

6. **Run the Application:**
   You can now run the application on your web server.

## Configuration

After installation, you may want to configure additional settings:

- **Email Notifications:** Configure SMTP settings for email notifications regarding membership renewals, class reminders, etc.
- **Payment Gateway:** Set up payment integration for online transactions.

## Usage

### Dashboard

Upon logging in, you will be greeted with the dashboard, which provides an overview of member activity, class schedules, and financial statistics.

## Admin Panel

The admin panel is a comprehensive management interface that allows you to oversee all operations of your gym. Key features include:

- **User Management:** Manage users and Admin roles.
- **Reports:** Generate reports on membership sales, attendance, and revenue.
- **Settings:** Adjust various application settings such as operating hours, fees, and class schedules.

## Database Structure

The database consists of several tables to manage different aspects of the gym's operations:

## Contribution

We welcome contributions! If you would like to contribute to GYM One, please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Submit a pull request.

**Translations:** Use Crowdin for translations to help us reach a wider audience.

## License

GYM One is open source software and is licensed under the CUSTOM license. You are free to use, modify and distribute this software as long as the original license is included.

## Contact

For any inquiries, suggestions, or feedback, feel free to contact us:

- **Email:** center@gymoneglobal.com
- **GitHub:** [Mayer Bálint](https://github.com/mayerbalintdev)

Thank you for choosing GYM One! We hope this software helps you manage your gym more efficiently.

--- 