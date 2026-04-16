# Script for populating the database. You can run it as:
#
#     mix run priv/repo/seeds.exs
#
# Inside the script, you can read and write to any of your
# repositories directly:
#
#     PhoenixApi.Repo.insert!(%PhoenixApi.SomeSchema{})
#
# We recommend using the bang functions (`insert!`, `update!`
# and so on) as they will fail if something goes wrong.

alias PhoenixApi.Repo
alias PhoenixApi.Accounts.User
alias PhoenixApi.Media.Photo

Repo.delete_all(Photo)
Repo.delete_all(User)

generate_api_token = fn ->
  32
  |> :crypto.strong_rand_bytes()
  |> Base.url_encode64(padding: false)
end

user1 =
  %User{}
  |> User.changeset(%{api_token: generate_api_token.()})
  |> Repo.insert!()

user2 =
  %User{}
  |> User.changeset(%{api_token: generate_api_token.()})
  |> Repo.insert!()

IO.puts("Created users:")
IO.puts("  User 1 - ID: #{user1.id}, Token: #{user1.api_token}")
IO.puts("  User 2 - ID: #{user2.id}, Token: #{user2.api_token}")

photos_user1 = [
  %{
    photo_url: "https://images.unsplash.com/photo-1506905925346-21bda4d32df4",
    camera: "Canon EOS R5",
    lens: "RF 24-70mm f/2.8 L IS USM",
    settings: "Manual mode, RAW",
    description: "Mountain landscape at sunrise with beautiful golden hour lighting",
    location: "Rocky Mountains, Colorado",
    focal_length: "35mm",
    aperture: "f/8",
    shutter_speed: "1/125",
    iso: 100,
    taken_at: ~U[2024-06-15 06:30:00Z],
    user_id: user1.id
  },
  %{
    photo_url: "https://images.unsplash.com/photo-1518791841217-8f162f1e1131",
    camera: "Sony A7 III",
    lens: "FE 85mm f/1.8",
    settings: "Aperture priority",
    description: "Portrait of a tabby cat with striking green eyes",
    location: "Home Studio",
    focal_length: "85mm",
    aperture: "f/2.8",
    shutter_speed: "1/200",
    iso: 400,
    taken_at: ~U[2024-07-20 14:15:00Z],
    user_id: user1.id
  },
  %{
    photo_url: "https://images.unsplash.com/photo-1519681393784-d120267933ba",
    camera: "Canon EOS R5",
    lens: "RF 15-35mm f/2.8 L IS USM",
    settings: "Manual mode, Long exposure",
    description: "Milky Way over a mountain range, astrophotography",
    location: "Death Valley, California",
    focal_length: "20mm",
    aperture: "f/2.8",
    shutter_speed: "25s",
    iso: 3200,
    taken_at: ~U[2024-08-10 03:00:00Z],
    user_id: user1.id
  }
]

photos_user2 = [
  %{
    photo_url: "https://images.unsplash.com/photo-1469474968028-56623f02e42e",
    camera: "Nikon Z6 II",
    lens: "NIKKOR Z 24-200mm f/4-6.3 VR",
    settings: "Aperture priority, VR on",
    description: "Serene lake reflection with forest in the background",
    location: "Lake Tahoe, Nevada",
    focal_length: "50mm",
    aperture: "f/11",
    shutter_speed: "1/60",
    iso: 200,
    taken_at: ~U[2024-05-25 08:45:00Z],
    user_id: user2.id
  },
  %{
    photo_url: "https://images.unsplash.com/photo-1514477917009-389c76a86b68",
    camera: "Fujifilm X-T4",
    lens: "XF 23mm f/1.4 R",
    settings: "Film simulation: Classic Chrome",
    description: "Street photography, urban architecture and people",
    location: "New York City, NY",
    focal_length: "23mm",
    aperture: "f/5.6",
    shutter_speed: "1/250",
    iso: 800,
    taken_at: ~U[2024-09-05 16:20:00Z],
    user_id: user2.id
  }
]

Enum.each(photos_user1, fn photo_attrs ->
  %Photo{}
  |> Photo.changeset(photo_attrs)
  |> Repo.insert!()
end)

Enum.each(photos_user2, fn photo_attrs ->
  %Photo{}
  |> Photo.changeset(photo_attrs)
  |> Repo.insert!()
end)

IO.puts("\nCreated #{length(photos_user1)} photos for user 1")
IO.puts("Created #{length(photos_user2)} photos for user 2")
IO.puts("\nSeeds completed successfully!")
IO.puts("\nYou can test the API with:")
IO.puts("  curl -H \"access-token: #{user1.api_token}\" http://localhost:4000/api/photos")
