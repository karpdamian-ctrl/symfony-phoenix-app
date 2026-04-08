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
- Uprościłem też warstwę lajków, usuwając ukryty stan z repozytorium. Zamiast ustawiać użytkownika metodą `setUser()`, przekazuję go teraz jawnie do metod repozytorium i serwisu.
- Dodałem również ochronę CSRF dla akcji `like` i `unlike` oraz testy sprawdzające brak i niepoprawny token.
- Na tym etapie zostawiam obsługę tego flow w kontrolerze, ponieważ logika nie jest jeszcze skomplikowana. Głębszy refaktor do osobnych klas typu action/use case miałby większy sens przy większej liczbie podobnych endpointów i rozwijaniu kolejnych requestów w tym obszarze.

## Jak używam AI

Najpierw sam analizuję problem i określam, co dokładnie chcę osiągnąć. Następnie zlecam wykonanie lub wsparcie rozwiązania narzędziu AI, najczęściej za pomocą konkretnych poleceń i precyzyjnego kontekstu, zamiast ogólnych promptów.

AI traktuję jako narzędzie wspierające implementację, analizę i porządkowanie pracy, a nie jako zamiennik własnych decyzji projektowych.
