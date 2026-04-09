defmodule PhoenixApiWeb.Plugs.RateLimitPhotoImports do
  @moduledoc false

  import Plug.Conn
  import Phoenix.Controller

  alias PhoenixApi.Media

  def init(opts), do: opts

  def call(%Plug.Conn{assigns: %{current_user: current_user}} = conn, _opts) do
    case Media.allow_photo_import(current_user.id) do
      :ok ->
        conn

      {:error, :user_limit_exceeded} ->
        conn
        |> put_status(:too_many_requests)
        |> json(%{
          errors: %{
            code: "user_rate_limit_exceeded",
            detail: "Photo import user rate limit exceeded"
          }
        })
        |> halt()

      {:error, :global_limit_exceeded} ->
        conn
        |> put_status(:too_many_requests)
        |> json(%{
          errors: %{
            code: "global_rate_limit_exceeded",
            detail: "Photo import global rate limit exceeded"
          }
        })
        |> halt()
    end
  end

  def call(conn, _opts), do: conn
end
