defmodule PhoenixApi.MediaTest do
  use PhoenixApi.DataCase

  alias PhoenixApi.Accounts.User
  alias PhoenixApi.Media
  alias PhoenixApi.Media.Photo
  alias PhoenixApi.RateLimit.PhotoImportLimiter
  alias PhoenixApi.Repo

  setup do
    :ok = PhotoImportLimiter.reset!()
  end

  describe "list_user_photos/1" do
    test "returns only photos that belong to the given user" do
      user = insert_user("media_context_user_token")
      other_user = insert_user("media_context_other_token")

      first_photo =
        %Photo{}
        |> Photo.changeset(%{
          photo_url: "https://example.com/user-photo-1.jpg",
          camera: "Canon EOS R5",
          user_id: user.id
        })
        |> Repo.insert!()

      second_photo =
        %Photo{}
        |> Photo.changeset(%{
          photo_url: "https://example.com/user-photo-2.jpg",
          camera: "Sony A7 IV",
          user_id: user.id
        })
        |> Repo.insert!()

      %Photo{}
      |> Photo.changeset(%{
        photo_url: "https://example.com/other-user-photo.jpg",
        camera: "Nikon Z6",
        user_id: other_user.id
      })
      |> Repo.insert!()

      photos = Media.list_user_photos(user.id)

      assert Enum.map(photos, & &1.id) |> Enum.sort() ==
               Enum.sort([first_photo.id, second_photo.id])

      assert Enum.all?(photos, &(&1.user_id == user.id))
    end

    test "returns empty list when user has no photos" do
      user = insert_user("media_context_empty_user_token")

      assert Media.list_user_photos(user.id) == []
    end
  end

  describe "allow_photo_import/1" do
    test "returns ok below the configured limits" do
      :ok = PhotoImportLimiter.reset!(user_limit: 1, global_limit: 10)

      assert :ok = Media.allow_photo_import(1)
    end

    test "returns user limit exceeded when user exceeds limit" do
      :ok = PhotoImportLimiter.reset!(user_limit: 1, global_limit: 10)

      assert :ok = Media.allow_photo_import(1)
      assert {:error, :user_limit_exceeded} = Media.allow_photo_import(1)
    end

    test "returns global limit exceeded when global limit is hit" do
      :ok = PhotoImportLimiter.reset!(user_limit: 10, global_limit: 2)

      assert :ok = Media.allow_photo_import(1)
      assert :ok = Media.allow_photo_import(2)
      assert {:error, :global_limit_exceeded} = Media.allow_photo_import(3)
    end
  end

  defp insert_user(token) do
    %User{}
    |> User.changeset(%{api_token: token})
    |> Repo.insert!()
  end
end
