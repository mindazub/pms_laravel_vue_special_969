# PMS — Project Management System

A full-stack project management application built with **Laravel 12**, **Vue 3**, **Inertia.js**, and **Tailwind CSS**.

Organize your work into **Projects**, each containing **Notes (tasks)** managed on a drag-and-drop **Kanban board**.

---

## Features

- **Project management** — Create, edit, and delete projects; track completion percentage at a glance
- **Kanban board** — Three columns: *To Do*, *In Progress*, *Done*; drag cards between columns
- **Task progress** — In-Progress notes show a clickable progress bar advancing through 0 → 25 → 50 → 75 → 100%; reaching 100% automatically moves the task to *Done*
- **Project completion** — Calculated from the ratio of done tasks; turns green at 100%
- **Full CRUD** — Create, edit, delete notes and projects via modal dialogs with icon buttons
- **Authentication** — Registration, login, email verification, password reset (Laravel Breeze)
- **Authorization** — Policy-based access control; users only see and edit their own data
- **Responsive UI** — Mobile-friendly layout built with Tailwind CSS utility classes

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.3) |
| Frontend | Vue 3 + Inertia.js |
| Styling | Tailwind CSS 3 |
| Build | Vite 6 |
| Database | SQLite (dev) / MySQL (prod) |
| Auth | Laravel Breeze |

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- npm

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/mindazub/pms_laravel_vue_special_969.git
cd pms_laravel_vue_special_969

# 2. Install PHP dependencies
composer install

# 3. Install JS dependencies
npm install

# 4. Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# 5. Run database migrations and seed sample data
php artisan migrate --seed

# 6. Build frontend assets
npm run build

# 7. Start the development server
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

### Default Test Account (after seeding)

| Field | Value |
|---|---|
| Email | test@example.com |
| Password | password |

## Development

```bash
# Run Vite in watch mode (hot-reload)
npm run dev

# Run tests
php artisan test

# Run code style fixer
./vendor/bin/pint
```

## Project Structure

```
app/
  Http/Controllers/   — ProjectController, NoteController
  Models/             — Project, Note, User
  Policies/           — NotePolicy
database/
  migrations/         — Projects + Notes schema
  seeders/            — Sample projects and notes
resources/js/
  Pages/Projects/     — Main Kanban page (Index.vue)
  Layouts/            — AuthenticatedLayout
routes/
  web.php             — Auth + resource routes
```

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
