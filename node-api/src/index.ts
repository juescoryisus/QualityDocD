import app from "./app";
import { logger } from "./lib/logger";

const PORT = process.env.PORT ?? 5000;

app.listen(PORT, () => {
  logger.info({ port: PORT }, "Server started");
});

app.get("/health", (_req, res) => {
  res.json({ status: "ok", time: new Date().toISOString() });
});