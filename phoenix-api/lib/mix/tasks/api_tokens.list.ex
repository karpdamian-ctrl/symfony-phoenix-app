defmodule Mix.Tasks.ApiTokens.List do
  @moduledoc false
  @shortdoc "Lists API tokens from the database"

  use Mix.Task

  import Ecto.Query

  alias PhoenixApi.Accounts.User
  alias PhoenixApi.Repo

  @impl Mix.Task
  def run(_args) do
    Mix.Task.run("app.start")

    users =
      Repo.all(
        from(user in User,
          order_by: [asc: user.id],
          select: %{id: user.id, api_token: user.api_token}
        )
      )

    case users do
      [] ->
        Mix.shell().info("No users found.")

      _ ->
        print_table(users)
    end
  end

  defp print_table(users) do
    id_header = "ID"
    token_header = "API TOKEN"

    id_width =
      users
      |> Enum.map(&Integer.to_string(&1.id))
      |> Enum.map(&String.length/1)
      |> Enum.max(fn -> String.length(id_header) end)
      |> max(String.length(id_header))

    token_width =
      users
      |> Enum.map(&String.length(&1.api_token))
      |> Enum.max(fn -> String.length(token_header) end)
      |> max(String.length(token_header))

    separator =
      "+" <>
        String.duplicate("-", id_width + 2) <>
        "+" <> String.duplicate("-", token_width + 2) <> "+"

    Mix.shell().info(separator)

    Mix.shell().info(
      "| #{String.pad_trailing(id_header, id_width)} | #{String.pad_trailing(token_header, token_width)} |"
    )

    Mix.shell().info(separator)

    Enum.each(users, fn user ->
      Mix.shell().info(
        "| #{String.pad_trailing(Integer.to_string(user.id), id_width)} | #{String.pad_trailing(user.api_token, token_width)} |"
      )
    end)

    Mix.shell().info(separator)
  end
end
