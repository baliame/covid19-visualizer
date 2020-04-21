#!/usr/bin/env bash

make build/app
docker run -p 8080:80 -v $(pwd):/app covid19/app
