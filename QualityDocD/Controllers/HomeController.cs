using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using QualityDocD.Services;

namespace QualityDocD.Controllers;

[Authorize]
public class HomeController : Controller
{
    private readonly DocumentService _svc;

    public HomeController(DocumentService svc) => _svc = svc;

    public async Task<IActionResult> Index()
    {
        var vm = await _svc.GetIndexAsync(null, null, null);
        return View(vm);
    }
}
