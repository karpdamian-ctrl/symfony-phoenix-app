defmodule PhoenixApi.Media do
  @moduledoc false

  import Ecto.Query

  alias PhoenixApi.Media.Photo
  alias PhoenixApi.RateLimit.PhotoImportLimiter
  alias PhoenixApi.Repo

  def list_user_photos(user_id) when is_integer(user_id) do
    Photo
    |> where([photo], photo.user_id == ^user_id)
    |> Repo.all()
  end

  def allow_photo_import(user_id) when is_integer(user_id) do
    PhotoImportLimiter.allow_import(user_id)
  end
end
