import { Router, type IRouter } from "express";

const router: IRouter = Router();

router.get("/healthz", async (_req, res): Promise<void> => {
  res.json({ status: "ok" });
});

export default router;