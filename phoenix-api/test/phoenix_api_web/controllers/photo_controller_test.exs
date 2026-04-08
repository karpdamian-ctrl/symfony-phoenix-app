defmodule PhoenixApiWeb.PhotoControllerTest do
  use PhoenixApiWeb.ConnCase

  alias PhoenixApi.Repo
  alias PhoenixApi.Accounts.User
  alias PhoenixApi.Media.Photo

  setup do
    taken_at = DateTime.from_naive!(~N[2026-04-08 20:15:00], "Etc/UTC")

    user =
      %User{}
      |> User.changeset(%{api_token: "valid_test_token_123"})
      |> Repo.insert!()

    other_user =
      %User{}
      |> User.changeset(%{api_token: "other_user_token_456"})
      |> Repo.insert!()

    photo1 =
      %Photo{}
      |> Photo.changeset(%{
        photo_url: "https://example.com/photo1.jpg",
        camera: "Canon EOS R5",
        lens: "RF 24-70mm f/2.8",
        settings: "Manual mode",
        description: "Beautiful sunset",
        location: "Beach",
        focal_length: "50mm",
        aperture: "f/2.8",
        shutter_speed: "1/200",
        iso: 100,
        taken_at: taken_at,
        user_id: user.id
      })
      |> Repo.insert!()

    photo2 =
      %Photo{}
      |> Photo.changeset(%{
        photo_url: "https://example.com/photo2.jpg",
        camera: "Sony A7III",
        lens: "FE 85mm f/1.8",
        description: "Portrait shot",
        location: "Studio",
        user_id: user.id
      })
      |> Repo.insert!()

    _other_photo =
      %Photo{}
      |> Photo.changeset(%{
        photo_url: "https://example.com/photo3.jpg",
        camera: "Nikon Z6",
        user_id: other_user.id
      })
      |> Repo.insert!()

    {:ok, user: user, other_user: other_user, photo1: photo1, photo2: photo2, taken_at: taken_at}
  end

  describe "GET /api/photos" do
    test "returns only default fields for authenticated user", %{conn: conn, photo1: photo1, photo2: photo2} do
      conn =
        conn
        |> put_req_header("access-token", "valid_test_token_123")
        |> get("/api/photos")

      assert json_response(conn, 200) == %{
               "photos" => [
                 %{"id" => photo1.id, "photo_url" => photo1.photo_url},
                 %{"id" => photo2.id, "photo_url" => photo2.photo_url}
               ]
             }
    end

    test "returns requested photo attributes", %{conn: conn, photo1: photo1, taken_at: taken_at} do
      conn =
        conn
        |> put_req_header("access-token", "valid_test_token_123")
        |> get("/api/photos?fields=camera,lens,description,location,settings,focal_length,aperture,shutter_speed,iso,taken_at")

      response = json_response(conn, 200)
      photos = response["photos"]

      refute photos == []

      first_photo = List.first(photos)
      assert Map.keys(first_photo) |> Enum.sort() == [
               "aperture",
               "camera",
               "description",
               "focal_length",
               "id",
               "iso",
               "lens",
               "location",
               "photo_url",
               "settings",
               "shutter_speed",
               "taken_at"
             ]
      assert first_photo["id"] == photo1.id
      assert first_photo["photo_url"] == photo1.photo_url
      assert first_photo["camera"] == photo1.camera
      assert first_photo["lens"] == photo1.lens
      assert first_photo["description"] == photo1.description
      assert first_photo["location"] == photo1.location
      assert first_photo["settings"] == photo1.settings
      assert first_photo["focal_length"] == photo1.focal_length
      assert first_photo["aperture"] == photo1.aperture
      assert first_photo["shutter_speed"] == photo1.shutter_speed
      assert first_photo["iso"] == photo1.iso
      assert first_photo["taken_at"] == DateTime.to_iso8601(taken_at)
    end

    test "ignores unknown requested fields", %{conn: conn, photo1: photo1} do
      conn =
        conn
        |> put_req_header("access-token", "valid_test_token_123")
        |> get("/api/photos?fields=camera,unknown_field")

      response = json_response(conn, 200)
      first_photo = List.first(response["photos"])

      assert first_photo["id"] == photo1.id
      assert first_photo["photo_url"] == photo1.photo_url
      assert first_photo["camera"] == photo1.camera
      refute Map.has_key?(first_photo, "unknown_field")
      assert Map.keys(first_photo) |> Enum.sort() == ["camera", "id", "photo_url"]
    end

    test "returns empty array when user has no photos", %{conn: conn} do
      _new_user =
        %User{}
        |> User.changeset(%{api_token: "new_user_token"})
        |> Repo.insert!()

      conn =
        conn
        |> put_req_header("access-token", "new_user_token")
        |> get("/api/photos")

      assert json_response(conn, 200) == %{"photos" => []}
    end

    test "returns 401 when access-token header is missing", %{conn: conn} do
      conn = get(conn, "/api/photos")

      assert json_response(conn, 401) == %{
               "errors" => %{"detail" => "Unauthorized"}
             }
    end

    test "returns 401 when access-token is invalid", %{conn: conn} do
      conn =
        conn
        |> put_req_header("access-token", "invalid_token")
        |> get("/api/photos")

      assert json_response(conn, 401) == %{
               "errors" => %{"detail" => "Unauthorized"}
             }
    end

    test "different users see only their own photos", %{conn: conn} do
      conn =
        conn
        |> put_req_header("access-token", "other_user_token_456")
        |> get("/api/photos?fields=camera")

      response = json_response(conn, 200)
      assert length(response["photos"]) == 1
      assert Enum.at(response["photos"], 0)["photo_url"] == "https://example.com/photo3.jpg"
      assert Enum.at(response["photos"], 0)["camera"] == "Nikon Z6"
    end
  end
end
