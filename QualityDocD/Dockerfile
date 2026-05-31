# ── Etapa 1: Compilación ──────────────────────────────────────────────────────
FROM mcr.microsoft.com/dotnet/sdk:9.0 AS build
WORKDIR /src

COPY QualityDocD.csproj ./
RUN dotnet restore

COPY . .
RUN dotnet publish -c Release -o /app/publish --no-restore

# ── Etapa 2: Runtime ──────────────────────────────────────────────────────────
FROM mcr.microsoft.com/dotnet/aspnet:9.0 AS runtime
WORKDIR /app

RUN adduser --disabled-password --gecos "" appuser \
    && mkdir -p /app/wwwroot/uploads \
    && chown -R appuser /app

COPY --from=build --chown=appuser /app/publish .

USER appuser

ENV ASPNETCORE_URLS=http://+:8000
ENV ASPNETCORE_ENVIRONMENT=Production

EXPOSE 8000
ENTRYPOINT ["dotnet", "QualityDocD.dll"]