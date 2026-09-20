FROM golang:1.27-alpine AS build

WORKDIR /src

RUN apk add --no-cache git ca-certificates

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o /worker .

FROM alpine:3.21

RUN apk add --no-cache ca-certificates

COPY --from=build /worker /worker

ENTRYPOINT ["/worker"]
