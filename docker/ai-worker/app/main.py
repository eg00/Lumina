from contextlib import asynccontextmanager
from fastapi import FastAPI, HTTPException, UploadFile, File, Form
from fastapi.responses import Response
from pydantic import BaseModel
from PIL import Image, ImageFilter, ImageOps
from sentence_transformers import SentenceTransformer
import os
import io
import logging
import numpy as np
from rembg import remove, new_session

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ai-worker")

_text_model = None
_image_model = None
_rembg_session = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("Pre-warming AI models on startup...")
    try:
        get_text_model()
        get_image_model()
        get_rembg_session()
        logger.info("All AI models pre-warmed successfully.")
    except Exception as e:
        logger.error(f"Error during AI model pre-warming: {e}")
    yield


app = FastAPI(title="Lumina AI Worker", lifespan=lifespan)


def get_text_model() -> SentenceTransformer:
    global _text_model
    if _text_model is None:
        model_name = os.getenv("CLIP_TEXT_MODEL", "sentence-transformers/clip-ViT-B-32-multilingual-v1")
        logger.info(f"Loading CLIP Text model: {model_name}...")
        _text_model = SentenceTransformer(model_name)
        logger.info("CLIP Text model loaded successfully.")
    return _text_model


def get_image_model() -> SentenceTransformer:
    global _image_model
    if _image_model is None:
        model_name = os.getenv("CLIP_IMAGE_MODEL", "clip-ViT-B-32")
        logger.info(f"Loading CLIP Image model: {model_name}...")
        _image_model = SentenceTransformer(model_name)
        logger.info("CLIP Image model loaded successfully.")
    return _image_model


def get_rembg_session():
    global _rembg_session
    if _rembg_session is None:
        model_name = os.getenv("REMBG_MODEL", "u2netp")
        logger.info(f"Loading rembg model session: {model_name}...")
        _rembg_session = new_session(model_name)
        logger.info("rembg model session loaded successfully.")
    return _rembg_session


class TextEmbedRequest(BaseModel):
    text: str


class ImageEmbedRequest(BaseModel):
    paths: list[str]


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/embed-text")
def embed_text(req: TextEmbedRequest):
    if not req.text.strip():
        raise HTTPException(status_code=400, detail="Text cannot be empty")

    model = get_text_model()
    embedding = model.encode(req.text, normalize_embeddings=True)
    return {"embedding": embedding.tolist()}


@app.post("/embed-images")
def embed_images(req: ImageEmbedRequest):
    model = get_image_model()
    results = {}

    for path in req.paths:
        try:
            if os.path.exists(path):
                with Image.open(path) as img:
                    rgb_img = img.convert("RGB")
                    emb = model.encode(rgb_img, normalize_embeddings=True)
                    results[path] = emb.tolist()
            else:
                logger.warning(f"File not found: {path}")
        except Exception as e:
            logger.error(f"Failed to process image {path}: {e}")

    return {"results": results}


@app.post("/portrait-blur")
async def portrait_blur(
    file: UploadFile = File(...),
    blur_radius: int = Form(20),
):
    try:
        contents = await file.read()
        raw_img = Image.open(io.BytesIO(contents))
        orig = ImageOps.exif_transpose(raw_img) or raw_img
        orig = orig.convert("RGB")
        session = get_rembg_session()

        # 1. Extract foreground alpha mask
        mask = remove(orig, session=session, only_mask=True)
        if mask.mode != "L":
            mask = mask.convert("L")
        if mask.size != orig.size:
            mask = mask.resize(orig.size, Image.Resampling.LANCZOS)

        # 2. Gaussian blur background
        blurred = orig.filter(ImageFilter.GaussianBlur(radius=blur_radius))

        # 3. Composite original foreground onto blurred background with mask
        composite = Image.composite(orig, blurred, mask)

        output = io.BytesIO()
        composite.save(output, format="JPEG", quality=95)
        return Response(content=output.getvalue(), media_type="image/jpeg")
    except Exception as e:
        logger.error(f"Portrait blur failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))


_tag_embedding_cache: dict = {}


class ClassifyTagsRequest(BaseModel):
    paths: list[str] | None = None
    image_path: str | None = None
    image_paths: list[str] | None = None
    candidate_tags: list[str] | None = None
    tags: list[str] | None = None
    candidates: list[str] | None = None
    labels: list[str] | None = None
    threshold: float = 0.26
    top_k: int = 5
    topK: int = 5
    limit: int = 5


def _candidate_list(req: ClassifyTagsRequest) -> list[str]:
    for field in (req.candidate_tags, req.tags, req.candidates, req.labels):
        if field:
            return [str(t).strip() for t in field if str(t).strip()]
    return []


def _image_paths(req: ClassifyTagsRequest) -> list[str]:
    paths: list[str] = []
    if req.image_path:
        paths.append(req.image_path)
    if req.image_paths:
        paths.extend(req.image_paths)
    if req.paths:
        paths.extend(req.paths)
    seen = set()
    out = []
    for p in paths:
        if p not in seen:
            seen.add(p)
            out.append(p)
    return out


@app.post("/classify-tags")
def classify_tags(req: ClassifyTagsRequest):
    candidate_tags = _candidate_list(req)
    paths = _image_paths(req)

    top_k = req.top_k or req.topK or req.limit or 5
    if top_k < 1:
        top_k = 5

    if not paths:
        return {"results": {}}

    if not candidate_tags:
        return {"results": {p: [] for p in paths}}

    text_model = get_text_model()
    image_model = get_image_model()

    # Cache text embeddings per tag
    missing = [t for t in candidate_tags if t not in _tag_embedding_cache]
    if missing:
        new_embs = text_model.encode(missing, normalize_embeddings=True)
        for t, emb in zip(missing, new_embs):
            _tag_embedding_cache[t] = np.asarray(emb, dtype=np.float32)

    tag_vectors = np.array([_tag_embedding_cache[t] for t in candidate_tags], dtype=np.float32)

    results = {}
    for path in paths:
        if not os.path.exists(path):
            logger.warning(f"File not found: {path}")
            results[path] = []
            continue
        try:
            with Image.open(path) as img:
                rgb_img = img.convert("RGB")
                img_emb = np.asarray(image_model.encode(rgb_img, normalize_embeddings=True), dtype=np.float32)

            scores = tag_vectors @ img_emb

            matched = []
            for idx, score in enumerate(scores):
                score_f = float(score)
                if score_f >= req.threshold:
                    matched.append({"tag": candidate_tags[idx], "score": score_f})

            matched.sort(key=lambda x: x["score"], reverse=True)
            results[path] = matched[:top_k]
        except Exception as e:
            logger.error(f"Error classifying {path}: {e}")
            results[path] = []

    return {"results": results}
