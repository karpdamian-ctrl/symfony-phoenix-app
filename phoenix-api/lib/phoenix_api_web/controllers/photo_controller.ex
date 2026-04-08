defmodule PhoenixApiWeb.PhotoController do
  use PhoenixApiWeb, :controller

  alias PhoenixApi.Repo
  alias PhoenixApi.Media.Photo
  import Ecto.Query

  @base_fields [:id, :photo_url]
  @optional_fields [
    :camera,
    :lens,
    :settings,
    :description,
    :location,
    :focal_length,
    :aperture,
    :shutter_speed,
    :iso,
    :taken_at
  ]

  plug(PhoenixApiWeb.Plugs.Authenticate)

  def index(conn, params) do
    current_user = conn.assigns.current_user
    fields = requested_fields(params)

    photos =
      Photo
      |> where([p], p.user_id == ^current_user.id)
      |> Repo.all()
      |> Enum.map(&serialize_photo(&1, fields))

    json(conn, %{photos: photos})
  end

  defp requested_fields(params) do
    params
    |> Map.get("fields", "")
    |> String.split(",", trim: true)
    |> Enum.map(&String.trim/1)
    |> Enum.reduce([], fn field, acc ->
      case safe_existing_atom(field) do
        {:ok, atom} when atom in @optional_fields -> [atom | acc]
        _ -> acc
      end
    end)
    |> Enum.reverse()
  end

  defp serialize_photo(photo, fields) do
    photo
    |> Map.take(@base_fields ++ fields)
  end

  defp safe_existing_atom(field) do
    {:ok, String.to_existing_atom(field)}
  rescue
    ArgumentError -> :error
  end
end
