# Notes

## Dziennik

### 2026-04-08

- Przejrzałem kod i obecny sposób uruchamiania obu aplikacji.
- Dodałem pipeline oraz podstawowe checki jakości dla Symfony i Phoenix.
- Naprawiłem problemy konfiguracyjne związane z Dockerem, testami i CI.
- Poprawiłem warningi oraz błędy wykryte przez narzędzia jakościowe, tak aby pipeline zaczął świecić się na zielono.
- W ramach porządkowania SymfonyApp zmieniam akcję like z `GET` na `POST` i rozdzielam ją na dwie osobne operacje: `like` oraz `unlike`.
- Poprzedni toggle był słabym rozwiązaniem, bo jedna akcja wykonywała dwie różne operacje zależnie od aktualnego stanu. To utrudniało czytelność kodu, testowanie i dalszą rozbudowę logiki. Dodatkowo użytkownik, wykonując konkretną i świadomą akcję, mógłby ją nieświadomie cofnąć przez opóźnienie internetu albo podwójne kliknięcie.
- Zauważyłem brak indeksu unikalnego dla lajków. Dodałem go na poziomie bazy, obsłużyłem przypadek podwójnego lajka w kodzie i dopisałem do tego testy.
- Uprościłem też warstwę lajków, tak aby metody repozytorium i serwisu dostawały użytkownika wprost w argumentach. Dzięki temu kod jest czytelniejszy i nie zależy od wcześniejszego ustawiania użytkownika „gdzieś obok”.
- Dodałem również ochronę CSRF dla akcji `like` i `unlike` oraz testy sprawdzające brak i niepoprawny token.
- Na tym etapie zostawiam obsługę tego flow w kontrolerze, ponieważ logika nie jest jeszcze skomplikowana. Głębszy refaktor do osobnych klas typu action/use case miałby większy sens przy większej liczbie podobnych endpointów i rozwijaniu kolejnych requestów w tym obszarze.
- Naprawiam też błąd logowania, w którym dowolny poprawny token pozwalał zalogować dowolnego użytkownika. Logowanie wymaga teraz poprawnego spięcia konkretnego tokenu z konkretnym użytkownikiem i jest pokryte testami.
- Przeniosłem też logowanie z linku `GET` na osobną podstronę z formularzem `POST` zabezpieczonym CSRF. W ten sam sposób przerobiłem wylogowanie, które zamiast zwykłego linku działa teraz jako `POST` z własnym tokenem CSRF.
- Wydzieliłem też część wspólną dla kontrolerów do bazowej klasy, żeby nie powielać logiki pobierania aktualnego użytkownika z sesji. Korzystają z tego teraz kontrolery auth, home, profile i photo.
- Po dalszym uporządkowaniu wydzieliłem odpowiedzialności do serwisów, tak aby kontrolery zostały możliwie cienkie i pełniły głównie rolę warstwy HTTP: przyjęcie requestu, walidacja wejścia, delegacja do serwisu i zwrócenie odpowiedzi.
- Wyniosłem też komunikaty do pliku tłumaczeń. To była prosta operacja, a w prawdziwym projekcie bardzo szybko mogłaby pojawić się potrzeba obsługi wielu języków, więc chciałem od razu przygotować kod pod taki scenariusz.
- Ułożyłem też kod SymfonyApp w bardziej modułowy sposób, rozdzielając pliki na obszary `Home`, `Auth`, `Photo`, `Profile`, `Shared` i pozostawiając `Likes` jako osobny moduł.
- Domknąłem również testy funkcjonalne dla wszystkich kontrolerów, tak aby każdy kontroler w projekcie miał własne pokrycie testowe.
- Dodałem też interfejsy dla serwisów i repozytoriów tam, gdzie miało to sens, a następnie przepiąłem dependency injection na kontrakty zamiast konkretnych implementacji.
- Doinstalowałem `pcov` do obrazu Symfony w `Dockerfile`, żeby móc generować pokrycie testami w kontenerze. W większym projekcie rozdzieliłbym obrazy bardziej precyzyjnie na środowiska dev/test/prod, ale na obecnym etapie byłoby to przerostem formy nad treścią.
- Dodałem do `README` badge'e pokazujące status pipeline'ów oraz pokrycie testami dla Symfony i Phoenix, tak aby jakość projektu była widoczna od razu z poziomu repozytorium.
- Dodałem też w profilu użytkownika formularz zapisu tokenu Phoenix API, tak aby użytkownik mógł samodzielnie podać i zapisać dane potrzebne do późniejszego importu zdjęć.
- Rozszerzyłem endpoint Phoenix `GET /api/photos` o whitelistę parametru `fields`, dzięki czemu domyślnie zwraca tylko podstawowe dane (`id`, `photo_url`), a dodatkowe atrybuty zdjęcia są dołączane wyłącznie wtedy, gdy klient jawnie o nie poprosi.
- Dodałem też unikalne ograniczenie dla zdjęć w Symfony po `user_id + image_url`, żeby baza pilnowała tej samej reguły co logika importu i nie pozwalała zapisać dwa razy tego samego zdjęcia dla jednego użytkownika.
- Wszystkie dotychczasowe testy Symfony związane z importem zdjęć korzystały z mocków/stubów odpowiedzi z Phoenixa, dzięki czemu zwykła paczka testów nie zależy od żyjącego zewnętrznego serwera. Żeby mimo to pokryć prawdziwy przepływ między aplikacjami, dodałem osobny test integracyjny odpy­tujący żywy serwer Phoenix.
- Ten test integracyjny nie jest częścią standardowej paczki i powinien być uruchamiany świadomie, np. komendą `composer test-integration`, przy działającym i zseedowanym serwerze Phoenix.
- Ponieważ mam już stabilny sposób uruchamiania obu aplikacji, podpiąłem ten test integracyjny również pod osobny job w GitHub Actions, żeby rzeczywiście pracował w CI, a nie był tylko testem uruchamianym ręcznie.

## Zadanie 1 - najważniejsze wprowadzone poprawki

- Naprawiłem błąd logowania, w którym poprawny token pozwalał zalogować dowolnego użytkownika. Logowanie wymaga teraz poprawnego powiązania konkretnego tokenu z konkretnym użytkownikiem i jest zabezpieczone testami.
- Rozdzieliłem wcześniejszy toggle lajków na dwa osobne endpointy `POST`: `like` i `unlike`. Dzięki temu zachowanie aplikacji jest bardziej przewidywalne, a kod łatwiejszy do rozwijania i testowania.
- Dodałem ochronę CSRF dla logowania, wylogowania oraz operacji `like/unlike`, razem z testami pokrywającymi brak i niepoprawny token.
- Dodałem indeks unikalny dla lajków na poziomie bazy i obsłużyłem przypadek podwójnego lajka po stronie aplikacji, tak aby logika była bezpieczna również przy równoległych requestach.
- Przeniosłem logikę biznesową z kontrolerów do serwisów (`AuthService`, `PhotoReactionService`), a kontrolery zostawiłem jako cienką warstwę HTTP odpowiedzialną za request, walidację i odpowiedź.
- Wydzieliłem wspólne elementy warstwy kontrolerów do `AppController`, żeby nie duplikować logiki pobierania użytkownika z sesji, walidacji CSRF i tłumaczeń.
- Uporządkowałem strukturę plików w bardziej modułowy układ (`Home`, `Auth`, `Photo`, `Profile`, `Shared`, `Likes`), dzięki czemu kod jest czytelniejszy i łatwiejszy do rozwijania w podziale na obszary funkcjonalne.
- Dodałem interfejsy dla istotnych serwisów i repozytoriów, żeby oprzeć zależności na kontraktach i lepiej przygotować kod pod dalszą rozbudowę oraz wymianę implementacji.
- Wyniosłem komunikaty do pliku tłumaczeń, żeby przygotować projekt pod łatwiejszą rozbudowę o kolejne języki.
- Domknąłem testy funkcjonalne wszystkich kontrolerów oraz rozszerzyłem testy jednostkowe serwisów i bazowego kontrolera, tak aby najważniejsze elementy warstwy HTTP i logiki aplikacyjnej były pokryte testami.

## Jak używam AI

Najpierw sam analizuję problem i określam, co dokładnie chcę osiągnąć. Następnie zlecam wykonanie lub wsparcie rozwiązania narzędziu AI, najczęściej za pomocą konkretnych poleceń i precyzyjnego kontekstu, zamiast ogólnych promptów.

AI traktuję jako narzędzie wspierające implementację, analizę i porządkowanie pracy, a nie jako zamiennik własnych decyzji projektowych.
