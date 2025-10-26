# Avatar Customization API

LPR

This is a Laravel-based API for creating and customizing avatars. Users can register, log in, and customize their avatars with various features like beards, crowns, dresses, eyes, hair, mouths, noses, and skin tones.

## Features

*   **User Authentication:** JWT-based authentication for user registration, login, and profile management.
*   **Avatar Customization:** A rich set of options for avatar customization.
*   **Product Management:** API endpoints for managing product categories.
*   **Scalable Architecture:** Built on the robust and scalable Laravel 12 framework.

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/ahsan-alam-500/tonycustom.git
    cd customize
    ```

2.  **Install dependencies:**
    ```bash
    composer install
    npm install
    ```

3.  **Set up environment variables:**
    ```bash
    cp .env.example .env
    ```
    *Update the `.env` file with your database credentials and other settings.*

4.  **Generate application key:**
    ```bash
    php artisan key:generate
    ```

5.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```

6.  **Run the development server:**
    ```bash
    php artisan serve
    ```

## API Endpoints

### Authentication

*   `POST /api/register`: Register a new user.
*   `POST /api/login`: Log in a user and get a JWT token.
*   `GET /api/auth/me`: Get the authenticated user's details.
*   `POST /api/auth/logout`: Log out the user.
*   `POST /api/auth/refresh`: Refresh the JWT token.

### Categories

*   `GET /api/categories`: Get a list of all categories.
*   `GET /api/categories/{id}`: Get a specific category.
*   `POST /api/categories`: Create a new category.
*   `PUT /api/categories/{id}`: Update a category.
*   `DELETE /api/categories/{id}`: Delete a category.

## Key Dependencies

*   [Laravel 12](https://laravel.com)
*   [Tymon JWT Auth](https://jwt-auth.readthedocs.io/en/develop/)
*   [PestPHP](https://pestphp.com/)

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).
