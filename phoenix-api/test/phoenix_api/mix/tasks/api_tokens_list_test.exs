defmodule PhoenixApi.Mix.Tasks.ApiTokensListTest do
  use PhoenixApi.DataCase

  alias PhoenixApi.Accounts.User
  alias PhoenixApi.Repo

  import ExUnit.CaptureIO

  setup do
    Mix.Task.reenable("api_tokens.list")
    :ok
  end

  test "prints users and tokens ordered by id" do
    user1 =
      %User{}
      |> User.changeset(%{api_token: "token_111"})
      |> Repo.insert!()

    user2 =
      %User{}
      |> User.changeset(%{api_token: "token_222"})
      |> Repo.insert!()

    output =
      capture_io(fn ->
        Mix.Task.run("api_tokens.list")
      end)

    assert output =~ "ID"
    assert output =~ "API TOKEN"
    assert output =~ Integer.to_string(user1.id)
    assert output =~ Integer.to_string(user2.id)
    assert output =~ "token_111"
    assert output =~ "token_222"
  end

  test "prints info when there are no users" do
    output =
      capture_io(fn ->
        Mix.Task.run("api_tokens.list")
      end)

    assert output =~ "No users found."
  end
end
