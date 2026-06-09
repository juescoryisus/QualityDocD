import { MongoClient, type Db } from "mongodb";

const MONGO_URI = process.env.MONGO_URI ?? "mongodb://localhost:27017";
const MONGO_DB  = process.env.MONGO_DB  ?? "qualitydoc_meta";

let client: MongoClient | null = null;
let db: Db | null = null;

export async function getMongoDb(): Promise<Db> {
  if (db) return db;
  client = new MongoClient(MONGO_URI, {
    connectTimeoutMS: 3000,
    serverSelectionTimeoutMS: 3000,
  });
  await client.connect();
  db = client.db(MONGO_DB);
  return db;
}

export async function closeMongoDb(): Promise<void> {
  if (client) { await client.close(); client = null; db = null; }
}

// Interface para los documentos en MongoDB
export interface MongoDocument {
  documentId: number;
  versionId: number;
  companyId: number;
  title: string;
  format: string;
  version: string;
  status: string;
  contentText: string;
  tags: string[];
  approvedAt: Date | null;
}