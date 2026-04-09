defmodule PhoenixApiWeb.Plugs.Authenticate do
  @moduledoc false

  import Plug.Conn
  import Phoenix.Controller
  alias PhoenixApi.Accounts

  def init(opts), do: opts

  def call(conn, _opts) do
    case get_req_header(conn, "access-token") do
      [token] ->
        case Accounts.authenticate_by_api_token(token) do
          {:ok, user} ->
            assign(conn, :current_user, user)

          {:error, :unauthorized} ->
            conn
            |> put_status(:unauthorized)
            |> put_view(json: PhoenixApiWeb.ErrorJSON)
            |> render(:"401")
            |> halt()
        end

      [] ->
        conn
        |> put_status(:unauthorized)
        |> put_view(json: PhoenixApiWeb.ErrorJSON)
        |> render(:"401")
        |> halt()
    end
  end
end
