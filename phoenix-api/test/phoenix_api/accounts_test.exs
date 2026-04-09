defmodule PhoenixApi.AccountsTest do
  use PhoenixApi.DataCase

  alias PhoenixApi.Accounts
  alias PhoenixApi.Accounts.User
  alias PhoenixApi.Repo

  describe "get_user_by_api_token/1" do
    test "returns the matching user" do
      user =
        %User{}
        |> User.changeset(%{api_token: "accounts_context_token"})
        |> Repo.insert!()

      user_id = user.id

      assert %User{id: ^user_id} = Accounts.get_user_by_api_token("accounts_context_token")
    end

    test "returns nil when token does not exist" do
      assert Accounts.get_user_by_api_token("missing_token") == nil
    end
  end

  describe "authenticate_by_api_token/1" do
    test "returns ok tuple with matching user" do
      user =
        %User{}
        |> User.changeset(%{api_token: "accounts_auth_token"})
        |> Repo.insert!()

      user_id = user.id

      assert {:ok, %User{id: ^user_id}} =
               Accounts.authenticate_by_api_token("accounts_auth_token")
    end

    test "returns unauthorized error when token does not exist" do
      assert {:error, :unauthorized} = Accounts.authenticate_by_api_token("missing_auth_token")
    end
  end
end
