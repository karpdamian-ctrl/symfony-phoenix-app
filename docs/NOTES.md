# Notes

Ten log powstawał na bieżąco w trakcie pracy. Przed pushowaniem kolejnych commitów zlecałem AI dopisywanie do niego wprowadzonych zmian oraz moich bieżących przemyśleń i decyzji roboczych.

Z treści zadania wynika dla mnie, że istotne jest nie tylko końcowe rozwiązanie, ale też sposób działania, podejmowania decyzji i iteracyjnego rozwijania projektu. Dlatego zostawiam ten dziennik w dość surowej formie, zamiast wygładzać go do finalnego, „raportowego” opisu.

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

### 2026-04-09

- Dodałem na stronie głównej filtrowanie zdjęć po `location`, `camera`, `description`, zakresie dat `taken_at_from` - `taken_at_to` oraz `username`, korzystając ze zwykłych zapytań do bazy danych przez repozytorium.
- Formularz filtrowania obsługuje dodatkowo przedział czasu `od-do` oraz walidację poprawnego formatu daty. W przypadku niepoprawnej daty użytkownik dostaje komunikat błędu zamiast mylącego zwrócenia pełnej listy zdjęć.
- Do takiego filtrowania mógłbym zaprzęgnąć Elasticsearch, ale na obecnym etapie uważam to za rozwiązanie nadmiarowe. Zakres danych i złożoność wyszukiwania są na tyle małe, że prostsze i rozsądniejsze jest pozostanie przy standardowych requestach do bazy.
- Dodałem też wyświetlanie daty zrobienia zdjęcia na kaflu, żeby użytkownik mógł wygodniej odczytać tę informację i filtrować zdjęcia również po dacie wykonania.
- W PhoenixApi zaimplementowałem rate limiting dla importu zdjęć z wykorzystaniem OTP (`GenServer`): pojedynczy użytkownik może wykonać maksymalnie 5 importów w ciągu 10 minut, a globalnie wszystkie importy są ograniczone do 1000 na godzinę.
- Po stronie SymfonyApp dodałem też obsługę błędu przekroczenia limitu importu, tak aby użytkownik zamiast ogólnego błędu dostał czytelny komunikat z informacją, że powinien spróbować ponownie za 10 minut.
- Doprecyzowałem również obsługę limitów importu tak, aby rozróżniać limit użytkownika i limit globalny. PhoenixApi zwraca teraz osobne błędy `429` dla obu przypadków, a SymfonyApp pokazuje użytkownikowi adekwatny komunikat zależnie od źródła ograniczenia: dla limitu użytkownika `Import limit reached for your account. Try again in 10 minutes.`, a dla limitu globalnego `Import limit reached for the service. Try again later.`.
- Uzupełniłem też `README` o bardziej szczegółowe opisy projektu oraz dodałem zrzuty ekranu aplikacji, żeby repozytorium lepiej pokazywało zakres wykonanej pracy i aktualny wygląd interfejsu.
- Po napisaniu działającej wersji aplikacji zrobiłem sobie kilka godzin przerwy, a potem wróciłem do projektu z nastawieniem na własny przegląd kodu. Traktuję ten etap jako wewnętrzny code review po czasie, bo właśnie wtedy najłatwiej zauważyć rzeczy, które umknęły w trakcie intensywnej implementacji.
- Pierwszy wychwycony problem dotyczył encji `Like`, która została w `src/Likes` zamiast trafić razem z pozostałymi encjami do `src/Entity`. Porządkuję to i przenoszę ją do właściwego miejsca, żeby struktura projektu była spójna.
- Zauważyłem też zbędną komplikację w warstwie obsługi reakcji na zdjęcia. `LikeService` dublował część odpowiedzialności `PhotoReactionService`, a przy tym wprowadzał niesymetryczny przepływ między `like` i `unlike`, więc uprościłem ten obszar przez usunięcie `LikeService` i zostawienie całej logiki reakcji w jednym miejscu.
- Przeniosłem też `PhoenixPhotoImportRateLimitException` z `src/Photo/Service` do `src/Photo/Exception`, żeby ujednolicić organizację wyjątków w module `Photo` i ułatwić dalsze utrzymanie kodu.
- Uporządkowałem też architekturę PhoenixApi, dodając contexty `Accounts` i `Media` oraz przepinając na nie kontroler i plugi. Dzięki temu warstwa web nie odwołuje się już bezpośrednio do `Repo`, schem i limitera, tylko korzysta ze spójnego API modułów domenowych.
- Do tego dopisałem testy dla nowych funkcji contextów, żeby zabezpieczyć ten refaktor.


## Jak używam AI

AI wykorzystuję przede wszystkim jako narzędzie do iteracyjnej współpracy przy implementacji, testach i porządkowaniu kodu. Zaczynam od własnej analizy problemu i wymagań, a następnie prowadzę pracę krok po kroku, dostarczając precyzyjny kontekst, wskazując konkretne pliki, doprecyzowując oczekiwany efekt i na bieżąco korygując kierunek, gdy rozwiązanie zaczyna odchodzić od założeń zadania.

W praktyce używam AI nie tylko do wygenerowania pierwszej wersji rozwiązania, ale też do jego dopracowania: zawężania zakresu do realnych wymagań, poprawiania testów, walidacji edge case'ów, porządkowania komunikatów błędów, notatek i drobnych kwestii jakościowych wykrywanych przez narzędzia takie jak PHPStan, PHP CS Fixer czy formatter Elixira.

AI traktuję jako wsparcie wykonawcze i analityczne, ale odpowiedzialność za decyzje projektowe, ocenę zgodności z wymaganiami i końcowy kształt rozwiązania pozostaje po mojej stronie.
