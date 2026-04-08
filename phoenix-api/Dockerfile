FROM elixir:1.15-alpine AS base

RUN apk add --no-cache build-base git nodejs npm inotify-tools bash

WORKDIR /app

RUN mix local.hex --force && mix local.rebar --force

FROM base AS dev

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 4000

ENTRYPOINT ["/entrypoint.sh"]

FROM base AS prod

ENV MIX_ENV=prod

COPY mix.exs mix.lock ./
COPY config ./config

RUN mix deps.get --only prod && mix deps.compile

COPY lib lib
COPY priv priv

RUN mix compile && mix release

EXPOSE 4000

CMD ["_build/prod/rel/phoenix_api/bin/phoenix_api", "start"]
