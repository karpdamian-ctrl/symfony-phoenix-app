defmodule PhoenixApi.Accounts do
  @moduledoc false

  alias PhoenixApi.Accounts.User
  alias PhoenixApi.Repo

  def get_user_by_api_token(token) when is_binary(token) do
    Repo.get_by(User, api_token: token)
  end

  def authenticate_by_api_token(token) when is_binary(token) do
    case get_user_by_api_token(token) do
      %User{} = user -> {:ok, user}
      nil -> {:error, :unauthorized}
    end
  end
end
