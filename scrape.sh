#!/usr/bin/env bash

make build/scraper
docker run -p 8080:80 -v $(pwd):/app covid19/scraper
