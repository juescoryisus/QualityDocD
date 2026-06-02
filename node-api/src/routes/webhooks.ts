import { Router, type IRouter } from "express";
import { ReceiveDocumentApprovedEventBody } from "../lib/schemas";
import { logger } from "../lib/logger";

const router: IRouter = Router();

router.post("/webhooks/document-approved", async (req, res): Promise<void> => {
  const parsed = ReceiveDocumentApprovedEventBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const { documentId, versionId, companyId, approvedAt } = parsed.data;

  logger.info(
    { documentId, versionId, companyId, approvedAt },
    "Received document-approved event from external service"
  );

  res.json({ received: true });
});

export default router;
