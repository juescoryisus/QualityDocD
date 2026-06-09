import { Router, type IRouter } from "express";
import healthRouter    from "./health";
import authRouter      from "./auth";
import companiesRouter from "./companies";
import documentsRouter from "./documents";
import searchRouter    from "./search";
import webhooksRouter  from "./webhooks";
import modulesRouter   from "./modules";   // ← NUEVO

const router: IRouter = Router();

router.use(healthRouter);
router.use(authRouter);
router.use(companiesRouter);
router.use(documentsRouter);
router.use(searchRouter);
router.use(webhooksRouter);
router.use(modulesRouter);   // ← NUEVO

export default router;