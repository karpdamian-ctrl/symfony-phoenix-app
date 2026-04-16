## Journal

### 2026-04-08

- I reviewed the codebase and the current way both applications are run.
- I added a pipeline and basic quality checks for Symfony and Phoenix.
- I fixed configuration issues related to Docker, tests, and CI.
- I resolved warnings and errors detected by quality tools so the pipeline started passing.
- As part of organizing SymfonyApp, I changed the like action from `GET` to `POST` and split it into two separate operations: `like` and `unlike`.
- The previous toggle was a weak solution because one action performed two different operations depending on current state. That reduced readability, made testing harder, and complicated further logic expansion. Additionally, a user performing a deliberate action could unintentionally revert it due to network delay or a double click.
- I noticed a missing unique index for likes. I added it at the database level, handled the double-like case in code, and added tests for it.
- I also simplified the likes layer so repository and service methods receive the user directly as an argument. This made the code clearer and removed dependence on setting the user somewhere else beforehand.
- I added CSRF protection for `like` and `unlike` actions and tests for missing and invalid tokens.
- At this stage I kept this flow inside the controller because the logic is not yet complex. A deeper refactor into separate action/use-case classes would make more sense with more similar endpoints and further requests in this area.
- I also fixed a login bug where any valid token could log in any user. Login now requires a correct token-user match and is covered by tests.
- I moved login from a `GET` link to a dedicated page with a CSRF-protected `POST` form. I applied the same change to logout, which now works as a `POST` with its own CSRF token instead of a regular link.
- I extracted common controller logic into a base class to avoid duplicating session-based current-user resolution. Auth, home, profile, and photo controllers now use it.
- After further cleanup, I moved responsibilities into services so controllers stay as thin as possible and mainly handle HTTP concerns: accept request, validate input, delegate to service, return response.
- I also moved messages to translation files. This was a simple step, and in a real project multilingual support can become necessary quickly, so I wanted to prepare the code for that scenario.
- I reorganized SymfonyApp into a more modular structure by splitting files into `Home`, `Auth`, `Photo`, `Profile`, `Shared`, and leaving `Likes` as a separate module.
- I completed functional tests for all controllers so every controller in the project has its own test coverage.
- I added interfaces for services and repositories where it made sense, then switched dependency injection to contracts instead of concrete implementations.
- I installed `pcov` in the Symfony image in `Dockerfile` to generate test coverage inside the container. In a larger project, I would split images more precisely by dev/test/prod environments, but at this stage that would be overengineering.
- I added badges to `README` showing pipeline status and test coverage for Symfony and Phoenix so project quality is visible directly in the repository.
- I also added a form in the user profile for saving a Phoenix API token so the user can provide and store data needed for later photo import.
- I extended the Phoenix `GET /api/photos` endpoint with a `fields` parameter whitelist, so by default it returns only basic data (`id`, `photo_url`), and additional photo attributes are included only when explicitly requested by the client.
- I added a unique constraint in Symfony for photos by `user_id + image_url` so the database enforces the same rule as import logic and prevents saving the same photo twice for one user.
- All existing Symfony tests related to photo import used mocked/stubbed Phoenix responses, so the regular test suite did not depend on a live external server. To still cover the real flow between apps, I added a separate integration test that calls a live Phoenix server.
- This integration test is not part of the standard suite and should be run intentionally, for example with `composer test-integration`, with a running and seeded Phoenix server.
- Since I already had a stable way to run both applications, I connected this integration test to a separate GitHub Actions job so it runs in CI and is not only a manually executed test.

### 2026-04-09

- I added photo filtering on the home page by `location`, `camera`, `description`, date range `taken_at_from` - `taken_at_to`, and `username`, using regular database queries through the repository.
- The filtering form also supports a `from-to` time range and validates proper date format. When the date is invalid, the user gets an error message instead of a misleading full photo list.
- I could have used Elasticsearch for this filtering, but at this stage I consider it unnecessary. The data scope and search complexity are small enough that standard database requests are simpler and more reasonable.
- I also added displaying the photo capture date on the card so users can read it more easily and filter photos by capture date as well.
- In PhoenixApi, I implemented rate limiting for photo imports using OTP (`GenServer`): a single user can perform at most 5 imports in 10 minutes, and globally all imports are limited to 1000 per hour.
- On the SymfonyApp side, I also added handling for import rate-limit errors so the user gets a clear message instead of a generic error, including information to retry in 10 minutes.
- I further refined import-limit handling to distinguish between user and global limits. PhoenixApi now returns separate `429` errors for both cases, and SymfonyApp shows a message appropriate to the source of the limit: for user limit `Import limit reached for your account. Try again in 10 minutes.`, and for global limit `Import limit reached for the service. Try again later.`.
- I also expanded `README` with more detailed project descriptions and added application screenshots so the repository better presents the scope of completed work and the current UI.
- After building a working version of the application, I took a few hours off and returned with a self-review mindset. I treat this stage as an internal delayed code review, because that is often when it is easiest to spot issues missed during intensive implementation.
- The first issue I found concerned the `Like` entity, which was in `src/Likes` instead of being placed with the other entities in `src/Entity`. I am organizing this and moving it to the proper location to keep project structure consistent.
- I also noticed unnecessary complexity in the photo reaction layer. `LikeService` duplicated part of `PhotoReactionService` responsibility and introduced an asymmetric flow between `like` and `unlike`, so I simplified this area by removing `LikeService` and keeping all reaction logic in one place.
- I moved `PhoenixPhotoImportRateLimitException` from `src/Photo/Service` to `src/Photo/Exception` to unify exception organization in the `Photo` module and make maintenance easier.
- I also cleaned up PhoenixApi architecture by adding `Accounts` and `Media` contexts and wiring the controller and plugs through them. Thanks to this, the web layer no longer calls `Repo`, schemas, and limiter directly, but uses a consistent domain-module API.
- I also added tests for the new context functions to secure this refactor.
- After another Symfony review, I improved the likes layer as well. I removed unnecessary responsibility mixing in `LikeRepository`, so the repository now handles only `Like` records, while photo counter updates remain in `PhotoReactionService`.
- I also improved home page performance by replacing per-item like checks in a loop with one bulk query for liked photos of the logged-in user.

### 2026-04-16

- I decided to add DTO-based filtering to the form on the home page.
- I translated `README` and `NOTES` into English.


## How I Use AI

I mainly use AI as a tool for iterative collaboration on implementation, testing, and code cleanup. I start with my own analysis of the problem and requirements, then work step by step, providing precise context, pointing to specific files, clarifying the expected result, and continuously adjusting direction when the solution starts drifting away from task assumptions.

In practice, I use AI not only to generate the first version of a solution, but also to refine it: narrowing scope to real requirements, improving tests, validating edge cases, organizing error messages and notes, and handling smaller quality issues detected by tools such as PHPStan, PHP CS Fixer, or the Elixir formatter.

I treat AI as execution and analytical support, but responsibility for design decisions, compliance with requirements, and the final shape of the solution remains on my side.
