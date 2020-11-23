# Shiplog Agent

## Setup env
```sh
cp .env.sample .env
```

## Dev
```sh
docker-compose up
```

## Posting file
```sh
curl -v 'http://localhost:8888/post/' \
-H 'Accept: application/json, text/plain, */*' \
-H 'Authorization: ApiKey 123456' \
-H 'Content-Type: application/json;charset=utf-8' \
--data-binary "{}"
```

Result should be:
```json
{"status":"ok","message":"File posted"}
```


## See posted files
Open url http://localhost:8888/uploads/

Username is shiplog and password is auth key in your env.

**Note:** you must post one file before accessing this folder!
