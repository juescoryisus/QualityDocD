import { Router, type IRouter } from "express";
import { db, companiesTable } from "../lib/db";
import { CreateCompanyBody } from "../lib/schemas";

const router: IRouter = Router();

router.post("/companies", async (req, res): Promise<void> => {
  const parsed = CreateCompanyBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const [company] = await db
    .insert(companiesTable)
    .values({
      name: parsed.data.name,
      slug: parsed.data.slug,
    })
    .returning();

  res.status(201).json(company);
});

export default router;
