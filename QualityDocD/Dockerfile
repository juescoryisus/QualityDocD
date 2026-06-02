FROM mcr.microsoft.com/dotnet/sdk:9.0 AS build
WORKDIR /src

COPY QualityDocD.csproj ./
RUN dotnet restore

COPY . .
RUN dotnet publish -c Release -o /app/publish

# ── Etapa 2: Runtime ──────────────────────────────────────────────────────────
FROM mcr.microsoft.com/dotnet/aspnet:9.0 AS runtime
WORKDIR /app

RUN adduser --disabled-password --gecos "" appuser \
    && mkdir -p /app/wwwroot/uploads \
    && chown -R appuser /app

COPY --from=build /app/publish .
RUN chown -R appuser /app

USER appuser
EXPOSE 5000
ENTRYPOINT ["dotnet", "QualityDocD.dll"]
