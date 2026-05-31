using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Filters;

namespace QualityDocD.Filters;

public class RequireAuthFilter : IAuthorizationFilter
{
    public void OnAuthorization(AuthorizationFilterContext context)
    {
        if (context.HttpContext.User.Identity?.IsAuthenticated != true)
        {
            var returnUrl = context.HttpContext.Request.Path
                          + context.HttpContext.Request.QueryString;
            context.Result = new RedirectToActionResult(
                "Login", "Auth", new { returnUrl });
        }
    }
}
