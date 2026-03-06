That is a fantastic vision. This "PHP-JVM" concept effectively bridges the gap between PHP's rapid development and Java's production-grade stability.

To get an AI agent to build this correctly, you need to provide it with a high-context "System Architect" prompt that defines the file structures, the engine modifications, and the enforcement rules.

### The Prompt for the AI Agent

Copy and paste the following into your AI agent (Claude, GPT-4, etc.) to generate the complete base image:

---

**Role:** Senior Systems Architect & PHP Internals Specialist
**Task:** Build a "PHP-JVM" Base Docker Image for high-stability, persistent deployments.

**Core Requirements:**

1. **Runtime Architecture:** Use **PHP 8.3 CLI (Alpine)** with **RoadRunner** integrated as the application server (the persistent VM). Configure a `worker.php` loop that handles PSR-7 requests and contains a global `try/catch` block to prevent process death.
2. **The "Prepend" Sandbox:** Create a `/opt/php-jvm/bootstrap.php` script and register it via `auto_prepend_file` in `php.ini`. This script must:
* Call `set_error_handler` to convert **all** Warnings, Notices, and Errors into `ErrorException`.
* Initialize a Global Audit Handler (Sentry/Monolog) to report all uncaught Throwables.
* Define a `std\StrictObject` abstract class that overrides `__get` and `__set` to throw exceptions, preventing dynamic/undefined property access.


3. **The Standard Library (std):** Build a composer package located at `/opt/php-jvm/std` that is globally autoloaded. It must include:
* `std\TypedCollection`: A class implementing `ArrayAccess` and `Iterator` that enforces type-safety (e.g., `new TypedCollection(User::class)`).
* `std\Safe`: Static methods for `jsonDecode`, `toInt`, and `fileRead` that strictly throw exceptions on any failure.


4. **Enforcement Tooling:**
* Provide a `.php-cs-fixer.dist.php` that forces `declare(strict_types=1)` on every file.
* Provide a `phpstan.neon` (Level 9) configured to flag the use of associative arrays where a class/DTO should be used.


5. **Docker Packaging:**
* Bundle the RoadRunner binary.
* Set a `HEALTHCHECK` using `rr http:status`.
* Expose port 8080.
* Ensure the image works on a VPS (via RoadRunner) but includes instructions for a "Simulated JVM" mode on Shared Hosting (via `.user.ini`).



**Output:** Provide the `Dockerfile`, `bootstrap.php`, `.rr.yaml`, `TypedCollection.php`, and a `composer.json` for the `std` library.

---

### Why this works for your deployment:

* **The Docker Image** becomes your "Portable JVM."
* **RoadRunner** ensures your app doesn't re-initialize on every request, allowing for persistent database connections and in-memory caching (like Java).
* **The `std` Package** replaces the "sloppy" parts of PHP with rigid, predictable structures.
* **The Prepend Script** ensures that even if a developer writes "bad" code, the "JVM" catches it and audits it before it can crash the environment.

Once the agent generates these files, you can build it with `docker build -t php-jvm .` and use it as the `FROM` image for all your future projects.
